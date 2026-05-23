<?php

namespace App\Services\Api\Shop;

use App\Exceptions\BusinessException;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * 解析或创建购物车。
     * - 登录用户优先用 customer_id
     * - 否则用 session_id（游客）
     * - 同租户同 shop 内同身份唯一活跃购物车
     */
    public function resolveOrCreate(
        int $tenantId,
        ?int $shopId,
        ?int $customerId,
        ?string $sessionId,
        ?string $locale = null,
        ?string $currency = null,
    ): Cart {
        if ($customerId === null && ($sessionId === null || $sessionId === '')) {
            throw new BusinessException('api.cart_identity_required');
        }

        $query = Cart::query()
            ->where('tenant_id', $tenantId)
            ->where('shop_id', $shopId);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        } else {
            $query->whereNull('customer_id')->where('session_id', $sessionId);
        }

        $cart = $query->first();
        if ($cart !== null) {
            return $cart;
        }

        return Cart::create([
            'tenant_id' => $tenantId,
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'session_id' => $customerId === null ? (string) $sessionId : '',
            'locale' => $locale ?: 'zh-CN',
            'currency' => $currency ?: 'CNY',
        ]);
    }

    public function getCartWithItems(Cart $cart): Cart
    {
        return $cart->load(['items.product.translations', 'items.variant.specificationValues.translations']);
    }

    /**
     * 添加 SKU 到购物车（已存在则合并数量）。
     */
    public function addItem(Cart $cart, int $variantId, int $quantity = 1): CartItem
    {
        if ($quantity < 1) {
            throw new BusinessException('api.invalid_cart_quantity');
        }

        $variant = ProductVariant::find($variantId);
        if ($variant === null) {
            throw new BusinessException('api.product_variant_not_found');
        }

        // SKU 所在商品必须属于同租户
        $product = Product::find($variant->product_id);
        if ($product === null || (int) $product->tenant_id !== (int) $cart->tenant_id) {
            throw new BusinessException('api.product_variant_not_found');
        }

        return DB::transaction(function () use ($cart, $variant, $product, $quantity) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('variant_id', $variant->id)
                ->first();

            if ($item !== null) {
                $item->quantity += $quantity;
                $item->save();

                return $item;
            }

            return CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => $quantity,
            ]);
        });
    }

    public function updateItemQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            throw new BusinessException('api.invalid_cart_quantity');
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * 游客购物车合并到登录用户购物车。
     * - 找游客 cart：(tenant, shop, session_id, customer_id IS NULL)
     * - 找登录 cart：(tenant, shop, customer_id)
     * - 合并 items：同 variant_id 数量相加；游客 cart 独有 item 转移
     * - 删除游客 cart
     */
    public function mergeGuestIntoCustomer(
        int $tenantId,
        ?int $shopId,
        string $sessionId,
        int $customerId,
    ): Cart {
        return DB::transaction(function () use ($tenantId, $shopId, $sessionId, $customerId) {
            $guestCart = Cart::where('tenant_id', $tenantId)
                ->where('shop_id', $shopId)
                ->whereNull('customer_id')
                ->where('session_id', $sessionId)
                ->first();

            $customerCart = $this->resolveOrCreate(
                $tenantId, $shopId, $customerId, null,
                $guestCart?->locale, $guestCart?->currency
            );

            if ($guestCart === null) {
                return $customerCart->load('items');
            }

            /** @var CartItem $guestItem */
            foreach ($guestCart->items()->get() as $guestItem) {
                $existing = CartItem::where('cart_id', $customerCart->id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->first();

                if ($existing !== null) {
                    $existing->quantity += $guestItem->quantity;
                    $existing->save();
                    $guestItem->delete();
                } else {
                    $guestItem->update(['cart_id' => $customerCart->id]);
                }
            }

            $guestCart->delete();

            return $customerCart->load('items');
        });
    }
}
