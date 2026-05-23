<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationTranslation;
use App\Models\Mall\SpecificationValue;
use App\Models\Mall\SpecificationValueTranslation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificationTest extends TestCase
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

    public function test_admin_can_list_specifications_of_own_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $tenantId = (int) auth()->user()->tenant_id;
        Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        Specification::create(['tenant_id' => $tenantId, 'code' => 'size']);

        $response = $this->getJson('/api/v1/mall/specifications');

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_admin_cannot_see_other_tenant_specs(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other', 'Other');

        Specification::create(['tenant_id' => $myTenantId, 'code' => 'color']);
        Specification::create(['tenant_id' => $other->id, 'code' => 'material']);

        $response = $this->getJson('/api/v1/mall/specifications');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('color', $response->json('data.list.0.code'));
    }

    public function test_super_admin_can_see_all_tenant_specs(): void
    {
        $this->actingAsSuperAdmin();

        $t1 = $this->createTenant('t1', 'T1');
        $t2 = $this->createTenant('t2', 'T2');
        Specification::create(['tenant_id' => $t1->id, 'code' => 'color']);
        Specification::create(['tenant_id' => $t2->id, 'code' => 'size']);

        $response = $this->getJson('/api/v1/mall/specifications');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_admin_can_create_specification_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/specifications', [
            'code' => 'color',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '颜色'],
                ['locale' => 'en-US', 'name' => 'Color'],
                ['locale' => 'ja-JP', 'name' => '色'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'color')
            ->assertJsonCount(3, 'data.translations');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('specifications', ['tenant_id' => $tenantId, 'code' => 'color']);
        $this->assertDatabaseHas('specification_translations', ['locale' => 'zh-CN', 'name' => '颜色']);
    }

    public function test_code_format_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/specifications', [
            'code' => 'Color',
        ]);

        $response->assertStatus(422);
    }

    public function test_code_unique_per_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $tenantId = (int) auth()->user()->tenant_id;
        Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);

        $response = $this->postJson('/api/v1/mall/specifications', [
            'code' => 'color',
        ]);

        $response->assertStatus(422);
    }

    public function test_same_code_allowed_across_tenants(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other', 'Other');
        Specification::create(['tenant_id' => $other->id, 'code' => 'color']);

        $response = $this->postJson('/api/v1/mall/specifications', [
            'code' => 'color',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('specifications', ['tenant_id' => $myTenantId, 'code' => 'color']);
        $this->assertDatabaseHas('specifications', ['tenant_id' => $other->id, 'code' => 'color']);
    }

    public function test_admin_cannot_update_other_tenant_spec(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other', 'Other');
        $spec = Specification::create(['tenant_id' => $other->id, 'code' => 'color']);

        $response = $this->putJson("/api/v1/mall/specifications/{$spec->id}", [
            'status' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_spec_with_cascading(): void
    {
        $this->actingAsSuperAdmin();
        $t = $this->createTenant('t1', 'T1');
        $spec = Specification::create(['tenant_id' => $t->id, 'code' => 'color']);
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '颜色']);
        $val = SpecificationValue::create(['specification_id' => $spec->id, 'code' => 'red', 'color_hex' => '#FF0000']);
        SpecificationValueTranslation::create(['specification_value_id' => $val->id, 'locale' => 'zh-CN', 'name' => '红']);

        $response = $this->deleteJson("/api/v1/mall/specifications/{$spec->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('specifications', ['id' => $spec->id]);
        $this->assertDatabaseMissing('specification_translations', ['specification_id' => $spec->id]);
        $this->assertDatabaseMissing('specification_values', ['id' => $val->id]);
        $this->assertDatabaseMissing('specification_value_translations', ['specification_value_id' => $val->id]);
    }

    public function test_unauthenticated_cannot_access_specs(): void
    {
        $this->getJson('/api/v1/mall/specifications')->assertStatus(401);
    }

    // === Specification Values ===

    public function test_admin_can_create_value_with_color_hex(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $spec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);

        $response = $this->postJson("/api/v1/mall/specifications/{$spec->id}/values", [
            'code' => 'red',
            'color_hex' => '#FF0000',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '红色'],
                ['locale' => 'en-US', 'name' => 'Red'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'red')
            ->assertJsonPath('data.color_hex', '#FF0000')
            ->assertJsonCount(2, 'data.translations');
    }

    public function test_color_hex_must_be_valid_format(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $spec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);

        $response = $this->postJson("/api/v1/mall/specifications/{$spec->id}/values", [
            'code' => 'red',
            'color_hex' => 'red',
        ]);

        $response->assertStatus(422);
    }

    public function test_value_code_unique_per_spec(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $spec = Specification::create(['tenant_id' => $tenantId, 'code' => 'color']);
        SpecificationValue::create(['specification_id' => $spec->id, 'code' => 'red']);

        $response = $this->postJson("/api/v1/mall/specifications/{$spec->id}/values", [
            'code' => 'red',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_list_values_of_spec(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $spec = Specification::create(['tenant_id' => $tenantId, 'code' => 'size']);
        SpecificationValue::create(['specification_id' => $spec->id, 'code' => 'm']);
        SpecificationValue::create(['specification_id' => $spec->id, 'code' => 'l']);

        $response = $this->getJson("/api/v1/mall/specifications/{$spec->id}/values");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_cannot_create_value_on_other_tenant_spec(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $other = $this->createTenant('other', 'Other');
        $spec = Specification::create(['tenant_id' => $other->id, 'code' => 'color']);

        $response = $this->postJson("/api/v1/mall/specifications/{$spec->id}/values", [
            'code' => 'red',
        ]);

        $response->assertStatus(403);
    }
}
