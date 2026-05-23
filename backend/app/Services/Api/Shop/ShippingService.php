<?php

namespace App\Services\Api\Shop;

use App\Models\Country;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\ShippingMethod;
use App\Models\Mall\ShippingRate;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;

/**
 * 运费计算（按 zone × 重量分段，含免运费阈值）。
 *
 * 计算流程：
 *   1. 收货国 country_code → Country → 关联的 zones[]（一国可属多 zone，如 CN ∈ APAC + ASEAN）
 *   2. 累计购物车总重量（按 variant.weight 各种单位 → 克）
 *   3. 用 PriceCalculator 算出订单目标币种小计（用于 free_threshold 比较）
 *   4. 找该租户启用的所有 ShippingMethod
 *   5. 对每个 method 在其 rates 里筛选 zone_id ∈ countryZones 且重量落在 [min, max] 的那条
 *      （weight_max=0 视为无上限）
 *   6. free_threshold > 0 且 subtotal ≥ free_threshold → 运费 0；否则取 rate.price
 *
 * 一个 method 可能在同 zone 有多条 rate（按重量分段），匹配第一条命中即返回。
 *
 * 返回 array 结构：
 *   [
 *     [
 *       'method_id'   => 1,
 *       'code'        => 'standard',
 *       'carrier'     => 'SF',
 *       'name'        => '普通快递' (locale fallback),
 *       'fee'         => 12.00,
 *       'is_free'     => false,
 *       'weight_g'    => 1500,
 *       'currency'    => 'CNY',
 *       'rate_id'     => 5,
 *     ],
 *     ...
 *   ]
 */
class ShippingService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * 给购物车 + 收货国家报价所有可用 method。
     *
     * @return array<int, array<string, mixed>>
     */
    public function quote(Cart $cart, string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            return [];
        }

        $zoneIds = $this->resolveZoneIds($countryCode);
        if ($zoneIds === []) {
            return [];
        }

        $items = $cart->items()->with('variant')->get();
        if ($items->isEmpty()) {
            return [];
        }

        $weightG = $this->computeTotalWeightG($items);
        $subtotal = $this->computeSubtotal($cart, $items);
        $currency = (string) ($cart->currency ?: 'CNY');

        $methods = ShippingMethod::query()
            ->with(['translations', 'rates'])
            ->where('tenant_id', $cart->tenant_id)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($methods as $method) {
            /** @var ShippingMethod $method */
            $rate = $this->matchRate($method, $zoneIds, $weightG);
            if ($rate === null) {
                continue;
            }

            $isFree = $this->isFreeShipping($rate, $subtotal);

            $result[] = [
                'method_id' => (int) $method->id,
                'code' => (string) $method->code,
                'carrier' => (string) $method->carrier,
                'name' => $this->resolveMethodName($method, (string) $cart->locale),
                'fee' => $isFree ? 0.0 : (float) $rate->price,
                'is_free' => $isFree,
                'weight_g' => $weightG,
                'currency' => $currency,
                'rate_id' => (int) $rate->id,
            ];
        }

        return $result;
    }

    /**
     * 取某个 method 的运费（用于下单时确定运费）。
     *
     * 不存在或不可用 → 返回 null（调用方决定怎么处理）。
     */
    public function calculate(Cart $cart, string $countryCode, int $methodId): ?float
    {
        foreach ($this->quote($cart, $countryCode) as $row) {
            if ($row['method_id'] === $methodId) {
                return (float) $row['fee'];
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveZoneIds(string $countryCode): array
    {
        $country = Country::query()
            ->where('code', $countryCode)
            ->with('zones:id')
            ->first();

        if ($country === null) {
            return [];
        }

        return $country->zones->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    /**
     * 累计购物车总重量（克）。
     *
     * @param  iterable<int, Model>  $items
     */
    private function computeTotalWeightG(iterable $items): int
    {
        $totalG = 0.0;
        foreach ($items as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }
            /** @var ProductVariant|null $variant */
            $variant = $item->variant;
            if ($variant === null) {
                continue;
            }
            $unit = strtolower((string) $variant->weight_unit);
            $weight = (float) $variant->weight;
            $perItemG = match ($unit) {
                'kg' => $weight * 1000.0,
                'oz' => $weight * 28.3495,
                'lb' => $weight * 453.592,
                default => $weight, // 默认 g
            };
            $totalG += $perItemG * (int) $item->quantity;
        }

        return (int) round($totalG);
    }

    /**
     * 累计购物车小计（cart 当前币种）。
     *
     * @param  iterable<int, Model>  $items
     */
    private function computeSubtotal(Cart $cart, iterable $items): float
    {
        $tenantId = (int) $cart->tenant_id;
        $currency = (string) ($cart->currency ?: 'CNY');

        $subtotal = 0.0;
        foreach ($items as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }
            /** @var ProductVariant|null $variant */
            $variant = $item->variant;
            if ($variant === null) {
                continue;
            }
            $unitPrice = $this->priceCalculator->computeForVariant(
                $variant,
                $currency,
                $tenantId,
            );
            $subtotal += $unitPrice * (int) $item->quantity;
        }

        return round($subtotal, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * 在 method.rates 里挑一条匹配 (zone, weight) 的费率；优先 weight_min 大的（更精细分段）。
     *
     * @param  array<int, int>  $zoneIds
     */
    private function matchRate(ShippingMethod $method, array $zoneIds, int $weightG): ?ShippingRate
    {
        $matched = [];
        foreach ($method->rates as $rate) {
            if (! $rate instanceof ShippingRate) {
                continue;
            }
            if (! in_array((int) $rate->zone_id, $zoneIds, true)) {
                continue;
            }
            $min = (int) $rate->weight_min;
            $max = (int) $rate->weight_max;
            if ($weightG < $min) {
                continue;
            }
            if ($max !== 0 && $weightG > $max) {
                continue;
            }
            $matched[] = $rate;
        }

        if ($matched === []) {
            return null;
        }

        // 优先 weight_min 大的（更精细的分段）
        usort($matched, static fn (ShippingRate $a, ShippingRate $b) => (int) $b->weight_min <=> (int) $a->weight_min);

        return $matched[0];
    }

    private function isFreeShipping(ShippingRate $rate, float $subtotal): bool
    {
        $threshold = (float) $rate->free_threshold;
        if ($threshold <= 0) {
            return false;
        }

        return $subtotal >= $threshold;
    }

    private function resolveMethodName(ShippingMethod $method, string $locale): string
    {
        $method->loadMissing('translations');
        $translation = $method->translations->firstWhere('locale', $locale)
            ?? $method->translations->first();

        if ($translation !== null) {
            return (string) $translation->getAttribute('name');
        }

        return (string) ($method->code ?: 'method-'.$method->id);
    }
}
