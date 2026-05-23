<?php

namespace Tests\Feature\Shop;

use App\Exceptions\BusinessException;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Shop\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReserveTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test', 'name' => 'Test', 'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $product = Product::create(['tenant_id' => $tenant->id]);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $this->service = app(InventoryService::class);
    }

    public function test_reserve_increases_reserved_count(): void
    {
        $this->service->reserve($this->variant->id, 3);

        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(3, $this->variant->fresh()->reserved);
    }

    public function test_reserve_fails_when_insufficient_stock(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->reserve($this->variant->id, 11);
    }

    public function test_reserve_respects_already_reserved(): void
    {
        $this->service->reserve($this->variant->id, 7);

        // 还剩 3 可用
        $this->expectException(BusinessException::class);
        $this->service->reserve($this->variant->id, 4);
    }

    public function test_reserve_zero_or_negative_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->reserve($this->variant->id, 0);
    }

    public function test_reserve_unknown_variant_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->reserve(99999, 1);
    }

    public function test_release_decreases_reserved(): void
    {
        $this->service->reserve($this->variant->id, 5);
        $this->service->release($this->variant->id, 2);

        $this->assertSame(3, $this->variant->fresh()->reserved);
        $this->assertSame(10, $this->variant->fresh()->stock); // stock 不变
    }

    public function test_release_does_not_go_negative(): void
    {
        $this->service->release($this->variant->id, 5);  // reserved 本是 0
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }

    public function test_confirm_deduct_reduces_both_stock_and_reserved(): void
    {
        $this->service->reserve($this->variant->id, 4);
        $this->service->confirmDeduct($this->variant->id, 4);

        $this->assertSame(6, $this->variant->fresh()->stock);    // 10 - 4
        $this->assertSame(0, $this->variant->fresh()->reserved); // 4 - 4
    }

    public function test_available_stock_excludes_reserved(): void
    {
        $this->service->reserve($this->variant->id, 3);

        $this->assertSame(7, $this->service->available($this->variant->id));
    }

    public function test_consecutive_reserves_accumulate_until_exhausted(): void
    {
        $this->service->reserve($this->variant->id, 5);
        $this->service->reserve($this->variant->id, 4);
        $this->assertSame(9, $this->variant->fresh()->reserved);

        // 11 之后应失败（仅剩 1）
        $this->expectException(BusinessException::class);
        $this->service->reserve($this->variant->id, 2);
    }

    public function test_release_after_confirm_deduct_does_not_increase_stock(): void
    {
        $this->service->reserve($this->variant->id, 3);
        $this->service->confirmDeduct($this->variant->id, 3);
        // 此时 stock=7 reserved=0
        $this->service->release($this->variant->id, 3); // 应不影响 stock

        $this->assertSame(7, $this->variant->fresh()->stock);
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }
}
