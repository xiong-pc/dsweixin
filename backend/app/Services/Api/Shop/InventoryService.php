<?php

namespace App\Services\Api\Shop;

use App\Exceptions\BusinessException;
use App\Models\Mall\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * 预占库存（下单时调用）。
     * - 锁 variant 行
     * - 校验 available_stock (= stock - reserved) >= qty
     * - reserved += qty
     *
     * @throws BusinessException 库存不足时抛出
     */
    public function reserve(int $variantId, int $quantity): ProductVariant
    {
        if ($quantity < 1) {
            throw new BusinessException('api.invalid_cart_quantity');
        }

        return DB::transaction(function () use ($variantId, $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->find($variantId);

            if ($variant === null) {
                throw new BusinessException('api.product_variant_not_found');
            }

            $available = (int) $variant->stock - (int) $variant->reserved;
            if ($available < $quantity) {
                throw new BusinessException('api.insufficient_stock');
            }

            $variant->reserved = (int) $variant->reserved + $quantity;
            $variant->save();

            return $variant;
        });
    }

    /**
     * 释放预占（取消订单/超时）。
     * reserved -= qty（不能小于 0）
     */
    public function release(int $variantId, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::withTrashed()->lockForUpdate()->find($variantId);

            if ($variant === null) {
                return;
            }

            $newReserved = max(0, (int) $variant->reserved - $quantity);
            $variant->reserved = $newReserved;
            $variant->save();
        });
    }

    /**
     * 确认扣减（支付成功）。
     * stock -= qty, reserved -= qty
     */
    public function confirmDeduct(int $variantId, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->find($variantId);

            if ($variant === null) {
                throw new BusinessException('api.product_variant_not_found');
            }

            $variant->stock = max(0, (int) $variant->stock - $quantity);
            $variant->reserved = max(0, (int) $variant->reserved - $quantity);
            $variant->save();
        });
    }

    /**
     * 退款 / 售后：把已经从 stock 扣减的数量加回去。
     *
     * 注意与 release() 的区别：
     *   - release() 仅在「预占未确认」阶段被调用，只回滚 reserved
     *   - restore() 在「已确认扣减」之后（订单已 Paid+）被调用，stock += qty
     */
    public function restore(int $variantId, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::withTrashed()->lockForUpdate()->find($variantId);

            if ($variant === null) {
                return;
            }

            $variant->stock = (int) $variant->stock + $quantity;
            $variant->save();
        });
    }

    /**
     * 仅查询可用库存（不加锁）。
     */
    public function available(int $variantId): int
    {
        $variant = ProductVariant::find($variantId);
        if ($variant === null) {
            return 0;
        }

        return max(0, (int) $variant->stock - (int) $variant->reserved);
    }
}
