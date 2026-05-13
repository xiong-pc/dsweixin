<?php

namespace App\Services\Api\Shop;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\ExchangeRate;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Order;
use App\Models\Mall\OrderAddress;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * 从购物车创建订单（快照机制 + 库存预占）。
     *
     * @param  array<string, mixed>  $addressData  shipping address fields
     * @param  array<string, mixed>  $extra  optional: billing_address, remark
     */
    public function createFromCart(Cart $cart, array $addressData, array $extra = []): Order
    {
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            throw new BusinessException('api.cart_is_empty');
        }

        return DB::transaction(function () use ($cart, $items, $addressData, $extra) {
            // 先预占所有 items 的库存（不足则整事务回滚）
            /** @var CartItem $cartItem */
            foreach ($items as $cartItem) {
                $this->inventoryService->reserve(
                    (int) $cartItem->variant_id,
                    (int) $cartItem->quantity
                );
            }

            $order = $this->createOrderShell($cart, $extra);

            $subtotal = 0.0;
            foreach ($items as $cartItem) {
                /** @var CartItem $cartItem */
                $itemModel = $this->snapshotItem($order, $cart, $cartItem);
                $subtotal += (float) $itemModel->line_total;
            }

            // P0 简化：运费、税费、折扣留 0；总额 = 商品小计
            $order->subtotal = $subtotal;
            $order->total = $subtotal + $order->shipping_fee + $order->tax_fee - $order->discount;
            $order->save();

            $this->saveAddress($order, 'shipping', $addressData);
            if (isset($extra['billing_address']) && is_array($extra['billing_address'])) {
                $this->saveAddress($order, 'billing', $extra['billing_address']);
            }

            // 下单后清空购物车
            $cart->items()->delete();

            return $order->fresh(['items', 'shippingAddress', 'billingAddress']) ?? $order;
        });
    }

    /**
     * 生成订单号：O + 时间戳 + 随机串。
     */
    public function generateOrderNo(): string
    {
        do {
            $no = 'O'.now()->format('YmdHis').Str::upper(Str::random(6));
        } while (Order::where('order_no', $no)->exists());

        return $no;
    }

    /**
     * 状态机：转移订单状态。
     */
    public function transitionStatus(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new BusinessException('api.invalid_order_status_transition');
        }

        $order->status = $target;
        $this->applyStatusTimestamps($order, $target);
        $order->save();
    }

    /**
     * 取消订单：释放所有预占库存。
     */
    public function cancelOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->transitionStatus($order, OrderStatus::Cancelled);

            /** @var OrderItem $item */
            foreach ($order->items()->get() as $item) {
                $this->inventoryService->release(
                    (int) $item->variant_id,
                    (int) $item->quantity
                );
            }
        });
    }

    /**
     * 支付成功：确认扣减库存（stock 真正减少）。
     */
    public function confirmPayment(Order $order, string $payMethod = ''): void
    {
        DB::transaction(function () use ($order, $payMethod) {
            $order->pay_method = $payMethod;
            $this->transitionStatus($order, OrderStatus::Paid);

            /** @var OrderItem $item */
            foreach ($order->items()->get() as $item) {
                $this->inventoryService->confirmDeduct(
                    (int) $item->variant_id,
                    (int) $item->quantity
                );
            }
        });
    }

    private function createOrderShell(Cart $cart, array $extra): Order
    {
        $order = new Order;
        $order->order_no = $this->generateOrderNo();
        $order->tenant_id = (int) $cart->tenant_id;
        $order->shop_id = $cart->shop_id;
        $order->customer_id = $cart->customer_id;
        $order->session_id = (string) $cart->session_id;
        $order->status = OrderStatus::Pending;
        $order->currency = (string) ($cart->currency ?: 'CNY');
        $order->exchange_rate = $this->resolveExchangeRate((int) $cart->tenant_id, $order->currency);

        if (isset($extra['remark']) && is_string($extra['remark'])) {
            $order->remark = $extra['remark'];
        }

        $order->save();

        return $order;
    }

    /**
     * 给某个 cart item 创建 order_item 快照。
     */
    private function snapshotItem(Order $order, Cart $cart, CartItem $cartItem): OrderItem
    {
        $variant = ProductVariant::find($cartItem->variant_id);
        if ($variant === null) {
            throw new BusinessException('api.product_variant_not_found');
        }

        $product = Product::find($variant->product_id);
        if ($product === null) {
            throw new BusinessException('api.product_variant_not_found');
        }

        $nameSnapshot = $this->resolveNameSnapshot($product, (string) $cart->locale);
        $imageSnapshot = $variant->image !== '' ? $variant->image : (string) $product->cover_image;
        $specSnapshot = $this->resolveSpecSnapshot($variant, (string) $cart->locale);

        $unitPrice = (float) $variant->price;
        $quantity = (int) $cartItem->quantity;
        $lineTotal = $unitPrice * $quantity;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => (string) $variant->sku,
            'name_snapshot' => $nameSnapshot,
            'image_snapshot' => $imageSnapshot,
            'spec_text_snapshot' => $specSnapshot,
            'unit_price' => $unitPrice,
            'currency' => (string) $order->currency,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }

    private function resolveNameSnapshot(Product $product, string $locale): string
    {
        $product->loadMissing('translations');

        $translation = $product->translations->firstWhere('locale', $locale);
        if ($translation !== null) {
            return (string) $translation->getAttribute('name');
        }

        // fallback 任意已存在 locale
        $fallback = $product->translations->first();
        if ($fallback !== null) {
            return (string) $fallback->getAttribute('name');
        }

        return (string) ($product->sku_prefix ?: 'Product '.$product->id);
    }

    private function resolveSpecSnapshot(ProductVariant $variant, string $locale): string
    {
        $variant->loadMissing('specificationValues.translations', 'specificationValues.specification.translations');

        $parts = [];
        foreach ($variant->specificationValues as $value) {
            /** @var SpecificationValue $value */
            $valueTrans = $value->translations->firstWhere('locale', $locale)
                ?? $value->translations->first();
            $valueName = $valueTrans !== null
                ? (string) $valueTrans->getAttribute('name')
                : (string) $value->code;

            $specName = '';
            /** @var Specification|null $spec */
            $spec = $value->specification;
            if ($spec !== null) {
                $specTrans = $spec->translations->firstWhere('locale', $locale)
                    ?? $spec->translations->first();
                $specName = $specTrans !== null
                    ? (string) $specTrans->getAttribute('name')
                    : (string) $spec->code;
            }

            $parts[] = $specName !== '' ? "{$specName}: {$valueName}" : $valueName;
        }

        return implode(' / ', $parts);
    }

    private function resolveExchangeRate(int $tenantId, string $currency): float
    {
        // P0 简化：CNY -> CNY 等同币种为 1.0；其他币种查 ExchangeRate 表（全局共享，无 tenant 字段）
        $base = 'CNY';
        if ($currency === $base) {
            return 1.0;
        }

        $rate = ExchangeRate::where('from_currency', $base)
            ->where('to_currency', $currency)
            ->latest('fetched_at')
            ->first();

        if ($rate === null) {
            return 1.0;
        }

        return (float) $rate->rate;
    }

    private function saveAddress(Order $order, string $type, array $data): OrderAddress
    {
        return OrderAddress::create([
            'order_id' => $order->id,
            'type' => $type,
            'country_code' => (string) ($data['country_code'] ?? ''),
            'province' => (string) ($data['province'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'district' => (string) ($data['district'] ?? ''),
            'street' => (string) ($data['street'] ?? ''),
            'postal_code' => (string) ($data['postal_code'] ?? ''),
            'contact_name' => (string) ($data['contact_name'] ?? ''),
            'contact_phone' => (string) ($data['contact_phone'] ?? ''),
            'contact_email' => (string) ($data['contact_email'] ?? ''),
        ]);
    }

    private function applyStatusTimestamps(Order $order, OrderStatus $target): void
    {
        $now = Carbon::now();
        match ($target) {
            OrderStatus::Paid => $order->paid_at = $now,
            OrderStatus::Shipped => $order->shipped_at = $now,
            OrderStatus::Delivered => $order->delivered_at = $now,
            OrderStatus::Cancelled => $order->cancelled_at = $now,
            OrderStatus::Refunded => $order->refunded_at = $now,
            default => null,
        };
    }
}
