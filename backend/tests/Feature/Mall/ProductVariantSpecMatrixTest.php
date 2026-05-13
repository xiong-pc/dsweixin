<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use App\Services\Api\Mall\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductVariantSpecMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductVariantService
    {
        return app(ProductVariantService::class);
    }

    public function test_matrix_generates_cartesian_product_of_two_groups(): void
    {
        // 颜色 (2) × 尺码 (3) = 6 个变体
        $combos = $this->service()->generateMatrix([[1, 2], [10, 11, 12]]);

        $this->assertCount(6, $combos);
        $this->assertContains([1, 10], $combos);
        $this->assertContains([1, 11], $combos);
        $this->assertContains([1, 12], $combos);
        $this->assertContains([2, 10], $combos);
        $this->assertContains([2, 11], $combos);
        $this->assertContains([2, 12], $combos);
    }

    public function test_matrix_generates_for_three_groups(): void
    {
        // 颜色 (2) × 尺码 (2) × 材质 (2) = 8
        $combos = $this->service()->generateMatrix([[1, 2], [10, 11], [100, 101]]);

        $this->assertCount(8, $combos);
    }

    public function test_matrix_single_group_returns_single_value_combos(): void
    {
        $combos = $this->service()->generateMatrix([[1, 2, 3]]);

        $this->assertCount(3, $combos);
        $this->assertSame([[1], [2], [3]], $combos);
    }

    public function test_matrix_empty_groups_returns_empty(): void
    {
        $this->assertSame([], $this->service()->generateMatrix([]));
        $this->assertSame([], $this->service()->generateMatrix([[]]));
    }

    public function test_matrix_filters_empty_inner_groups(): void
    {
        $combos = $this->service()->generateMatrix([[1, 2], [], [10]]);

        $this->assertCount(2, $combos);
        $this->assertContains([1, 10], $combos);
        $this->assertContains([2, 10], $combos);
    }

    public function test_matrix_api_endpoint_returns_combinations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = Product::create(['tenant_id' => $tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'T 恤']);

        $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants/matrix", [
            'spec_groups' => [[1, 2], [10, 11]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 4);

        $combinations = $response->json('data.combinations');
        $this->assertCount(4, $combinations);
    }

    public function test_matrix_endpoint_requires_at_least_one_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = Product::create(['tenant_id' => $tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'X']);

        $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants/matrix", [
            'spec_groups' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_matrix_endpoint_enforces_tenant_isolation(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $myTenantId = (int) auth()->user()->tenant_id;

        // 别人租户的商品
        $otherProduct = Product::create(['tenant_id' => $myTenantId + 999]);
        $otherProduct->translations()->create(['locale' => 'zh-CN', 'name' => '别人的']);

        $response = $this->postJson("/api/v1/mall/products/{$otherProduct->id}/variants/matrix", [
            'spec_groups' => [[1]],
        ]);

        $response->assertStatus(403);
    }

    public function test_creating_variants_from_matrix_results(): void
    {
        // 集成测试：先生成矩阵，然后用结果创建多个变体
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = Product::create(['tenant_id' => $tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'T 恤']);

        $colorSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        $red = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'red']);
        $blue = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'blue']);

        $sizeSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'size']);
        $m = SpecificationValue::create(['specification_id' => $sizeSpec->id, 'code' => 'm']);
        $l = SpecificationValue::create(['specification_id' => $sizeSpec->id, 'code' => 'l']);

        $combos = $this->service()->generateMatrix([[$red->id, $blue->id], [$m->id, $l->id]]);

        $skuIndex = 1;
        foreach ($combos as $combo) {
            $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants", [
                'sku' => 'TS-'.$skuIndex++,
                'specification_value_ids' => $combo,
                'stock' => 50,
            ]);
            $response->assertOk();
        }

        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(8, DB::table('product_variant_specification_values')->count());
    }
}
