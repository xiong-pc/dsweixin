<?php

namespace App\Services\Api\Shop;

use App\Models\ExchangeRate;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;

/**
 * 价格三段式计算：base × (1 + markup%) × 汇率。
 *
 * 三段含义：
 *   1. base_price          —— SKU 销售价（以 product.base_currency 为准）
 *   2. tenant markup       —— 租户 price_markup_pct（百分比上浮）
 *   3. currency conversion —— 与目标币种之间的 ExchangeRate 转换
 *
 * 所有结果保留 2 位小数（HALF_UP 四舍五入）。
 */
class PriceCalculator
{
    /**
     * 直接计算：传入基价与上下文，返回目标币种价格。
     */
    public function compute(
        float $basePrice,
        string $baseCurrency,
        string $targetCurrency,
        float $markupPct = 0.0,
    ): float {
        if ($basePrice <= 0) {
            return 0.0;
        }

        // 1. 加价
        $markupPct = max(0.0, $markupPct);
        $afterMarkup = $basePrice * (1 + $markupPct / 100);

        // 2. 汇率转换
        $rate = $this->resolveExchangeRate($baseCurrency, $targetCurrency);
        $final = $afterMarkup * $rate;

        return $this->roundCurrency($final);
    }

    /**
     * 根据 variant 计算最终售价。
     * - product.base_currency 作为基准币种
     * - tenantId 用于读取 markup
     * - targetCurrency 默认与 base 一致
     */
    public function computeForVariant(
        ProductVariant $variant,
        ?string $targetCurrency = null,
        ?int $tenantId = null,
    ): float {
        $product = $variant->product()->first();
        $baseCurrency = $product instanceof Product
            ? (string) ($product->base_currency ?: 'CNY')
            : 'CNY';

        $target = $targetCurrency !== null && $targetCurrency !== ''
            ? $targetCurrency
            : $baseCurrency;

        $markup = $this->resolveMarkup($tenantId);

        return $this->compute(
            (float) $variant->price,
            $baseCurrency,
            $target,
            $markup,
        );
    }

    /**
     * 单独暴露汇率查询，便于订单、购物车保存快照。
     */
    public function resolveExchangeRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->latest('fetched_at')
            ->first();

        if ($rate === null) {
            return 1.0;
        }

        return (float) $rate->rate;
    }

    /**
     * 单独暴露 markup 查询。
     */
    public function resolveMarkup(?int $tenantId): float
    {
        if ($tenantId === null || $tenantId <= 0) {
            return 0.0;
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return 0.0;
        }

        return max(0.0, (float) $tenant->price_markup_pct);
    }

    /**
     * 四舍五入到 0.01。
     */
    private function roundCurrency(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
}
