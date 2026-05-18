<?php

namespace App\Services\Api\Shop;

use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;

/**
 * Checkout 预览服务：在不写库的前提下，根据当前购物车计算最终价格、库存可用情况，
 * 让前端在用户点「去支付」前展示明细。
 *
 * 与 OrderService.createFromCart 区别：
 *   - 不预占库存（不写 reserved）
 *   - 不创建 order/order_items/order_addresses
 *   - 不清空购物车
 */
class CheckoutService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * 计算预览数据。
     *
     * @return array<string, mixed>
     */
    public function preview(Cart $cart): array
    {
        $items = $cart->items()->orderBy('id')->get();
        $tenantId = (int) $cart->tenant_id;
        $currency = (string) ($cart->currency ?: 'CNY');
        $rate = $this->priceCalculator->resolveExchangeRate('CNY', $currency);

        $previewItems = [];
        $subtotal = 0.0;
        $allStockOk = true;

        /** @var CartItem $cartItem */
        foreach ($items as $cartItem) {
            $variant = ProductVariant::find($cartItem->variant_id);
            if ($variant === null) {
                continue;
            }

            $product = Product::find($variant->product_id);
            if ($product === null) {
                // 孤立 variant（product 已删除），preview 跳过
                continue;
            }

            $unitPrice = $this->priceCalculator->computeForVariant($variant, $currency, $tenantId);
            $quantity = (int) $cartItem->quantity;
            $lineTotal = round($unitPrice * $quantity, 2, PHP_ROUND_HALF_UP);
            $available = $this->inventoryService->available((int) $variant->id);
            $stockOk = $available >= $quantity;
            if (! $stockOk) {
                $allStockOk = false;
            }

            $previewItems[] = [
                'cart_item_id' => (int) $cartItem->id,
                'variant_id' => (int) $variant->id,
                'product_id' => (int) $product->id,
                'sku' => (string) $variant->sku,
                'name' => $this->resolveName($product, (string) $cart->locale),
                'image' => $variant->image !== ''
                    ? (string) $variant->image
                    : (string) $product->cover_image,
                'spec_text' => $this->resolveSpec($variant, (string) $cart->locale),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'currency' => $currency,
                'available_stock' => $available,
                'stock_ok' => $stockOk,
            ];

            $subtotal += $lineTotal;
        }

        $subtotal = round($subtotal, 2, PHP_ROUND_HALF_UP);
        // P0：运费/税/折扣留 0；P1 接入运费引擎与税费规则后填充
        $shippingFee = 0.0;
        $taxFee = 0.0;
        $discount = 0.0;
        $total = round($subtotal + $shippingFee + $taxFee - $discount, 2, PHP_ROUND_HALF_UP);

        return [
            'cart_id' => (int) $cart->id,
            'tenant_id' => $tenantId,
            'shop_id' => $cart->shop_id !== null ? (int) $cart->shop_id : null,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'locale' => (string) ($cart->locale ?: ''),
            'items' => $previewItems,
            'item_count' => count($previewItems),
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'tax_fee' => $taxFee,
            'discount' => $discount,
            'total' => $total,
            'is_ready_to_place' => count($previewItems) > 0 && $allStockOk,
        ];
    }

    private function resolveName(Product $product, string $locale): string
    {
        $product->loadMissing('translations');
        $translation = $product->translations->firstWhere('locale', $locale)
            ?? $product->translations->first();
        if ($translation !== null) {
            return (string) $translation->getAttribute('name');
        }

        return (string) ($product->sku_prefix ?: 'Product '.$product->id);
    }

    private function resolveSpec(ProductVariant $variant, string $locale): string
    {
        $variant->loadMissing('specificationValues.translations', 'specificationValues.specification.translations');

        $parts = [];
        /** @var SpecificationValue $value */
        foreach ($variant->specificationValues as $value) {
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
}
