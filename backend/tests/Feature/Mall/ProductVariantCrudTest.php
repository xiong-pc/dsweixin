<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $code): Tenant
    {
        return Tenant::create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => 1,
            'primary_domain' => "{$code}.example.com",
        ]);
    }

    private function createProduct(int $tenantId): Product
    {
        $product = Product::create(['tenant_id' => $tenantId, 'sku_prefix' => 'P-001']);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '商品']);

        return $product;
    }

    public function test_admin_can_list_variants_of_product(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);

        ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-1', 'price' => 100]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-2', 'price' => 120]);

        $response = $this->getJson("/api/v1/mall/products/{$product->id}/variants");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_variant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);

        $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants", [
            'sku' => 'TS-RED-M',
            'barcode' => '1234567890123',
            'price' => 199.99,
            'compare_at_price' => 249.99,
            'cost' => 80.00,
            'weight' => 200.5,
            'weight_unit' => 'g',
            'dimensions' => ['l' => 30, 'w' => 20, 'h' => 5, 'unit' => 'cm'],
            'stock' => 100,
            'low_stock_threshold' => 10,
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sku', 'TS-RED-M')
            ->assertJsonPath('data.price', '199.99')
            ->assertJsonPath('data.stock', 100);

        $this->assertDatabaseHas('product_variants', ['sku' => 'TS-RED-M', 'product_id' => $product->id]);
    }

    public function test_sku_must_be_globally_unique(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $p1 = $this->createProduct($tenantId);
        $p2 = $this->createProduct($tenantId);

        ProductVariant::create(['product_id' => $p1->id, 'sku' => 'SHARED-SKU', 'price' => 100]);

        $response = $this->postJson("/api/v1/mall/products/{$p2->id}/variants", [
            'sku' => 'SHARED-SKU',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_variant_with_specification_values_creates_pivot(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);

        $colorSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        $red = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'red', 'color_hex' => '#FF0000']);

        $sizeSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'size']);
        $m = SpecificationValue::create(['specification_id' => $sizeSpec->id, 'code' => 'm']);

        $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants", [
            'sku' => 'TS-RED-M',
            'specification_value_ids' => [$red->id, $m->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variant_specification_values', [
            'specification_value_id' => $red->id,
        ]);
        $this->assertDatabaseHas('product_variant_specification_values', [
            'specification_value_id' => $m->id,
        ]);
    }

    public function test_update_variant_replaces_specification_values(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V1', 'price' => 100]);

        $colorSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        $red = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'red']);
        $blue = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'blue']);

        $variant->specificationValues()->sync([$red->id]);

        $response = $this->putJson("/api/v1/mall/product-variants/{$variant->id}", [
            'specification_value_ids' => [$blue->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variant_specification_values', [
            'product_variant_id' => $variant->id,
            'specification_value_id' => $blue->id,
        ]);
        $this->assertDatabaseMissing('product_variant_specification_values', [
            'product_variant_id' => $variant->id,
            'specification_value_id' => $red->id,
        ]);
    }

    public function test_available_stock_excludes_reserved(): void
    {
        $tenantId = 1;
        $product = $this->createProduct($tenantId);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V1',
            'stock' => 100,
            'reserved' => 30,
        ]);

        $this->assertSame(70, $variant->available_stock);
    }

    public function test_available_stock_does_not_go_negative(): void
    {
        $tenantId = 1;
        $product = $this->createProduct($tenantId);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V2',
            'stock' => 5,
            'reserved' => 10,
        ]);

        $this->assertSame(0, $variant->available_stock);
    }

    public function test_soft_delete_variant_detaches_spec_values(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);

        $colorSpec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        $red = SpecificationValue::create(['specification_id' => $colorSpec->id, 'code' => 'red']);

        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V1', 'price' => 100]);
        $variant->specificationValues()->sync([$red->id]);

        $response = $this->deleteJson("/api/v1/mall/product-variants/{$variant->id}");

        $response->assertOk();
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('product_variant_specification_values', [
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_unauthenticated_cannot_access_variants(): void
    {
        $tenantId = 1;
        $product = $this->createProduct($tenantId);

        $this->getJson("/api/v1/mall/products/{$product->id}/variants")->assertStatus(401);
    }

    public function test_admin_cannot_access_other_tenant_variants(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $product = $this->createProduct($other->id);

        $response = $this->getJson("/api/v1/mall/products/{$product->id}/variants");
        $response->assertStatus(403);
    }

    public function test_dimensions_field_is_json_cast(): void
    {
        $tenantId = 1;
        $product = $this->createProduct($tenantId);
        $dims = ['l' => 30.5, 'w' => 20.5, 'h' => 5.5, 'unit' => 'cm'];
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V1',
            'dimensions' => $dims,
        ]);

        // JSON cast 双向：保存为 JSON 字符串，读取还原为数组
        $fresh = $variant->fresh()->dimensions;
        $this->assertIsArray($fresh);
        $this->assertEquals($dims['l'], $fresh['l']);
        $this->assertEquals($dims['w'], $fresh['w']);
        $this->assertEquals($dims['h'], $fresh['h']);
        $this->assertSame('cm', $fresh['unit']);
    }

    public function test_weight_unit_must_be_valid(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = $this->createProduct($tenantId);

        $response = $this->postJson("/api/v1/mall/products/{$product->id}/variants", [
            'sku' => 'V1',
            'weight_unit' => 'kilogram', // invalid
        ]);

        $response->assertStatus(422);
    }
}
