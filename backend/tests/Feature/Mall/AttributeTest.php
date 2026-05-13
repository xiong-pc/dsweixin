<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Attribute;
use App\Models\Mall\AttributeTranslation;
use App\Models\Mall\AttributeValue;
use App\Models\Mall\AttributeValueTranslation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $code, string $name): Tenant
    {
        return Tenant::create([
            'code' => $code,
            'name' => $name,
            'status' => 1,
            'primary_domain' => "{$code}.example.com",
        ]);
    }

    public function test_admin_can_list_attributes_of_own_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $tenantId = (int) auth()->user()->tenant_id;
        Attribute::create(['tenant_id' => $tenantId, 'code' => 'material']);
        Attribute::create(['tenant_id' => $tenantId, 'code' => 'origin']);

        $response = $this->getJson('/api/v1/mall/attributes');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_admin_cannot_see_other_tenant_attributes(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other', 'Other');

        Attribute::create(['tenant_id' => $myTenantId, 'code' => 'material']);
        Attribute::create(['tenant_id' => $other->id, 'code' => 'season']);

        $response = $this->getJson('/api/v1/mall/attributes');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('material', $response->json('data.list.0.code'));
    }

    public function test_super_admin_can_see_all_tenant_attributes(): void
    {
        $this->actingAsSuperAdmin();

        $t1 = $this->createTenant('t1', 'T1');
        $t2 = $this->createTenant('t2', 'T2');
        Attribute::create(['tenant_id' => $t1->id, 'code' => 'material']);
        Attribute::create(['tenant_id' => $t2->id, 'code' => 'origin']);

        $response = $this->getJson('/api/v1/mall/attributes');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_admin_can_create_attribute_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/attributes', [
            'code' => 'material',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '材质'],
                ['locale' => 'en-US', 'name' => 'Material'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'material')
            ->assertJsonCount(2, 'data.translations');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('attributes', ['tenant_id' => $tenantId, 'code' => 'material']);
        $this->assertDatabaseHas('attribute_translations', ['locale' => 'zh-CN', 'name' => '材质']);
    }

    public function test_attribute_does_not_have_color_hex_field(): void
    {
        // 与 specification_values 的区别：attribute_values 没有 color_hex 字段
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $attr = Attribute::create(['tenant_id' => $tenantId, 'code' => 'material']);

        $response = $this->postJson("/api/v1/mall/attributes/{$attr->id}/values", [
            'code' => 'cotton',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '棉'],
            ],
        ]);

        $response->assertOk()->assertJsonMissingPath('data.color_hex');
    }

    public function test_attribute_code_format_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/attributes', [
            'code' => 'Material',
        ]);

        $response->assertStatus(422);
    }

    public function test_attribute_code_unique_per_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $tenantId = (int) auth()->user()->tenant_id;
        Attribute::create(['tenant_id' => $tenantId, 'code' => 'material']);

        $response = $this->postJson('/api/v1/mall/attributes', [
            'code' => 'material',
        ]);

        $response->assertStatus(422);
    }

    public function test_same_attribute_code_allowed_across_tenants(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other', 'Other');
        Attribute::create(['tenant_id' => $other->id, 'code' => 'material']);

        $response = $this->postJson('/api/v1/mall/attributes', [
            'code' => 'material',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attributes', ['tenant_id' => $myTenantId, 'code' => 'material']);
    }

    public function test_admin_cannot_update_other_tenant_attribute(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other', 'Other');
        $attr = Attribute::create(['tenant_id' => $other->id, 'code' => 'material']);

        $response = $this->putJson("/api/v1/mall/attributes/{$attr->id}", [
            'status' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_attribute_with_cascading(): void
    {
        $this->actingAsSuperAdmin();
        $t = $this->createTenant('t1', 'T1');
        $attr = Attribute::create(['tenant_id' => $t->id, 'code' => 'material']);
        AttributeTranslation::create(['attribute_id' => $attr->id, 'locale' => 'zh-CN', 'name' => '材质']);
        $val = AttributeValue::create(['attribute_id' => $attr->id, 'code' => 'cotton']);
        AttributeValueTranslation::create(['attribute_value_id' => $val->id, 'locale' => 'zh-CN', 'name' => '棉']);

        $response = $this->deleteJson("/api/v1/mall/attributes/{$attr->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('attributes', ['id' => $attr->id]);
        $this->assertDatabaseMissing('attribute_translations', ['attribute_id' => $attr->id]);
        $this->assertDatabaseMissing('attribute_values', ['id' => $val->id]);
        $this->assertDatabaseMissing('attribute_value_translations', ['attribute_value_id' => $val->id]);
    }

    public function test_unauthenticated_cannot_access_attributes(): void
    {
        $this->getJson('/api/v1/mall/attributes')->assertStatus(401);
    }

    // === Attribute Values ===

    public function test_admin_can_create_attribute_value_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $attr = Attribute::create(['tenant_id' => $tenantId, 'code' => 'origin']);

        $response = $this->postJson("/api/v1/mall/attributes/{$attr->id}/values", [
            'code' => 'china',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '中国'],
                ['locale' => 'en-US', 'name' => 'China'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'china')
            ->assertJsonCount(2, 'data.translations');
    }

    public function test_attribute_value_code_unique_per_attr(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $attr = Attribute::create(['tenant_id' => $tenantId, 'code' => 'origin']);
        AttributeValue::create(['attribute_id' => $attr->id, 'code' => 'china']);

        $response = $this->postJson("/api/v1/mall/attributes/{$attr->id}/values", [
            'code' => 'china',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_list_values_of_attribute(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $attr = Attribute::create(['tenant_id' => $tenantId, 'code' => 'origin']);
        AttributeValue::create(['attribute_id' => $attr->id, 'code' => 'china']);
        AttributeValue::create(['attribute_id' => $attr->id, 'code' => 'japan']);

        $response = $this->getJson("/api/v1/mall/attributes/{$attr->id}/values");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_cannot_create_value_on_other_tenant_attribute(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $other = $this->createTenant('other', 'Other');
        $attr = Attribute::create(['tenant_id' => $other->id, 'code' => 'material']);

        $response = $this->postJson("/api/v1/mall/attributes/{$attr->id}/values", [
            'code' => 'cotton',
        ]);

        $response->assertStatus(403);
    }

    public function test_has_translations_trait_works_on_attribute_model(): void
    {
        // 验证 trait 在 Attribute 模型上也正常运转
        $tenantId = 1;
        $attr = Attribute::create(['tenant_id' => $tenantId, 'code' => 'material']);
        $attr->setTranslations([
            ['locale' => 'zh-CN', 'name' => '材质'],
            ['locale' => 'en-US', 'name' => 'Material'],
        ]);

        $this->assertSame('材质', $attr->fresh()->getTranslation('zh-CN')->name);
        $this->assertSame('Material', $attr->fresh()->getTranslation('en-US')->name);
        // fallback
        $this->assertSame('Material', $attr->fresh()->getTranslation('ko-KR', 'en-US')->name);
    }
}
