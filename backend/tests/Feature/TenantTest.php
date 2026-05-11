<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'name'   => '测试租户',
            'code'   => 'TEST_' . uniqid(),
            'status' => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/tenants  —— 超级管理员视角
    // -------------------------------------------------------

    public function test_super_admin_can_list_all_tenants(): void
    {
        $this->actingAsSuperAdmin();
        $this->createTenant();
        $this->createTenant();

        $response = $this->getJson('/api/v1/tenants');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total']]);
    }

    public function test_super_admin_can_filter_tenants_by_keywords(): void
    {
        $this->actingAsSuperAdmin();
        $this->createTenant(['name' => '独特租户名称', 'code' => 'UNIQUE_T']);
        $this->createTenant(['name' => '其他租户']);

        $response = $this->getJson('/api/v1/tenants?keywords=独特');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
    }

    // -------------------------------------------------------
    // POST /api/v1/tenants  —— 仅超管可建
    // -------------------------------------------------------

    public function test_super_admin_can_create_tenant(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name'          => '新租户',
            'code'          => 'NEW_TENANT',
            'status'        => 1,
            'contact_name'  => '联系人',
            'contact_phone' => '13900000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.code', 'NEW_TENANT');

        $this->assertDatabaseHas('tenants', ['code' => 'NEW_TENANT']);
    }

    public function test_non_super_admin_cannot_create_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => '非法创建',
            'code' => 'ILLEGAL',
        ]);

        // FormRequest authorize() 返回 false → 403
        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_unique_code(): void
    {
        $this->actingAsSuperAdmin();
        $this->createTenant(['code' => 'DUP_TENANT']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => '重复编码',
            'code' => 'DUP_TENANT',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/tenants/{id}
    // -------------------------------------------------------

    public function test_super_admin_can_view_any_tenant(): void
    {
        $this->actingAsSuperAdmin();
        $tenant = $this->createTenant(['name' => '被查租户', 'code' => 'VIEW_T']);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'VIEW_T');
    }

    // -------------------------------------------------------
    // PUT /api/v1/tenants/{id}
    // -------------------------------------------------------

    public function test_super_admin_can_update_tenant(): void
    {
        $this->actingAsSuperAdmin();
        $tenant = $this->createTenant();

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}", [
            'name' => '修改后名称',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => '修改后名称']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/tenants/{id}  —— 仅超管可删
    // -------------------------------------------------------

    public function test_super_admin_can_delete_tenant(): void
    {
        $this->actingAsSuperAdmin();
        $tenant = $this->createTenant();

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_non_super_admin_cannot_delete_tenant(): void
    {
        $defaultTenant = $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->deleteJson("/api/v1/tenants/{$defaultTenant->id}");

        $response->assertStatus(403);
    }
}
