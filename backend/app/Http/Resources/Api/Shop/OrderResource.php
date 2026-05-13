<?php

namespace App\Http\Resources\Api\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'order_no' => data_get($r, 'order_no'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'shop_id' => data_get($r, 'shop_id'),
            'customer_id' => data_get($r, 'customer_id'),
            'status' => $this->formatStatus(data_get($r, 'status')),
            'currency' => data_get($r, 'currency'),
            'exchange_rate' => data_get($r, 'exchange_rate'),
            'subtotal' => data_get($r, 'subtotal'),
            'shipping_fee' => data_get($r, 'shipping_fee'),
            'tax_fee' => data_get($r, 'tax_fee'),
            'discount' => data_get($r, 'discount'),
            'total' => data_get($r, 'total'),
            'pay_method' => data_get($r, 'pay_method'),
            'paid_at' => $this->formatDateTime(data_get($r, 'paid_at')),
            'shipped_at' => $this->formatDateTime(data_get($r, 'shipped_at')),
            'shipping_no' => data_get($r, 'shipping_no'),
            'shipping_company' => data_get($r, 'shipping_company'),
            'delivered_at' => $this->formatDateTime(data_get($r, 'delivered_at')),
            'cancelled_at' => $this->formatDateTime(data_get($r, 'cancelled_at')),
            'refunded_at' => $this->formatDateTime(data_get($r, 'refunded_at')),
            'remark' => data_get($r, 'remark'),
            'items' => $this->formatItems(data_get($r, 'items')),
            'shipping_address' => $this->formatAddress(data_get($r, 'shippingAddress')),
            'billing_address' => $this->formatAddress(data_get($r, 'billingAddress')),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    private function formatStatus(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return $status === null ? null : (string) $status;
    }

    private function formatItems(mixed $items): array
    {
        if (! is_iterable($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => data_get($item, 'id'),
                'product_id' => data_get($item, 'product_id'),
                'variant_id' => data_get($item, 'variant_id'),
                'sku' => data_get($item, 'sku'),
                'name' => data_get($item, 'name_snapshot'),
                'image' => data_get($item, 'image_snapshot'),
                'spec_text' => data_get($item, 'spec_text_snapshot'),
                'unit_price' => data_get($item, 'unit_price'),
                'currency' => data_get($item, 'currency'),
                'quantity' => (int) data_get($item, 'quantity'),
                'line_total' => data_get($item, 'line_total'),
            ];
        }

        return $result;
    }

    private function formatAddress(mixed $addr): ?array
    {
        if ($addr === null) {
            return null;
        }

        return [
            'country_code' => data_get($addr, 'country_code'),
            'province' => data_get($addr, 'province'),
            'city' => data_get($addr, 'city'),
            'district' => data_get($addr, 'district'),
            'street' => data_get($addr, 'street'),
            'postal_code' => data_get($addr, 'postal_code'),
            'contact_name' => data_get($addr, 'contact_name'),
            'contact_phone' => data_get($addr, 'contact_phone'),
            'contact_email' => data_get($addr, 'contact_email'),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
