<?php

namespace App\Services\Api\Shop;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Order;
use App\Models\Mall\OrderAddress;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use App\Observers\OrderObserver;
use App\Services\Api\Mall\OrderStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly PriceCalculator $priceCalculator,
        private readonly OrderStateMachine $stateMachine,
    ) {}

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
     *
     * @param  array<string, mixed>  $context  可选：{reason, note, operator_type, operator_id} ——
     *                                         用于 OrderHistory 审计（由 OrderObserver 读取）
     */
    public function transitionStatus(Order $order, OrderStatus $target, array $context = []): void
    {
        $this->stateMachine->assertCanTransition($order->status, $target);

        // 在 save 之前把 reason/operator 上下文写到 OrderObserver 静态表，
        // observer 在 updated() 钩子里读出来写 OrderHistory，然后清理。
        if ($context !== []) {
            OrderObserver::setContext($order, $context);
        }

        $order->status = $target;
        $this->applyStatusTimestamps($order, $target);
        $order->save();
    }

    /**
     * 取消订单：释放所有预占库存。
     *
     * @param  array<string, mixed>  $context  可选审计：{reason, note, operator_type, operator_id}
     */
    public function cancelOrder(Order $order, array $context = []): void
    {
        DB::transaction(function () use ($order, $context) {
            $this->transitionStatus($order, OrderStatus::Cancelled, $context);

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
        // 平台基准币种 CNY 与订单目标币种之间的汇率快照（PriceCalculator 第三段）
        $order->exchange_rate = $this->priceCalculator->resolveExchangeRate('CNY', $order->currency);

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

        // 价格三段式：base × (1 + markup%) × 汇率
        $unitPrice = $this->priceCalculator->computeForVariant(
            $variant,
            (string) $order->currency,
            (int) $order->tenant_id,
        );
        $quantity = (int) $cartItem->quantity;
        $lineTotal = round($unitPrice * $quantity, 2, PHP_ROUND_HALF_UP);

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
