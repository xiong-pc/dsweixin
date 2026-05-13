<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    private function createPlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'code' => 'TEST_'.strtoupper(uniqid()),
            'name' => '测试套餐',
            'price_monthly' => 99.00,
            'max_shops' => 1,
            'max_products' => 100,
            'status' => 1,
        ], $attrs));
    }

    public function test_anyone_authenticated_can_list_plans(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $this->createPlan(['code' => 'BASIC', 'sort' => 10]);
        $this->createPlan(['code' => 'PREMIUM', 'sort' => 20]);

        $response = $this->getJson('/api/v1/system/plans');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total']]);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_unauthenticated_cannot_access_plans(): void
    {
        $response = $this->getJson('/api/v1/system/plans');

        $response->assertStatus(401);
    }

    public function test_super_admin_can_create_plan(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/plans', [
            'code' => 'BASIC',
            'name' => '基础版',
            'description' => '入门套餐',
            'price_monthly' => 99.00,
            'max_shops' => 2,
            'max_products' => 200,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.code', 'BASIC')
            ->assertJsonPath('data.max_shops', 2);

        $this->assertDatabaseHas('plans', ['code' => 'BASIC']);
    }

    public function test_default_values_applied_when_creating(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/plans', [
            'code' => 'MINIMAL',
            'name' => '极简版',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.currency', 'CNY')
            ->assertJsonPath('data.billing_period', 'monthly')
            ->assertJsonPath('data.status', 1);
    }

    public function test_admin_cannot_create_plan(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/plans', [
            'code' => 'HACK',
            'name' => '非超管尝试',
        ]);

        $response->assertStatus(403);
    }

    public function test_plan_code_must_be_unique(): void
    {
        $this->createPlan(['code' => 'DUPE']);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/plans', [
            'code' => 'DUPE',
            'name' => '重复',
        ]);

        $response->assertStatus(422);
    }

    public function test_plan_code_format_validation(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/plans', [
            'code' => 'lower-case',
            'name' => '非法编码',
        ]);

        $response->assertStatus(422);
    }

    public function test_super_admin_can_view_plan_detail(): void
    {
        $this->actingAsSuperAdmin();
        $plan = $this->createPlan(['code' => 'VIEW_P', 'name' => '查看套餐']);

        $response = $this->getJson("/api/v1/system/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'VIEW_P')
            ->assertJsonPath('data.name', '查看套餐');
    }

    public function test_admin_can_view_plan_detail(): void
    {
        // 普通管理员也能看套餐详情（用于前端选套餐）
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $plan = $this->createPlan(['code' => 'ADMIN_VIEW']);

        $response = $this->getJson("/api/v1/system/plans/{$plan->id}");

        $response->assertOk();
    }

    public function test_super_admin_can_update_plan(): void
    {
        $this->actingAsSuperAdmin();
        $plan = $this->createPlan(['code' => 'EDIT_P', 'name' => '原名']);

        $response = $this->putJson("/api/v1/system/plans/{$plan->id}", [
            'name' => '改名',
            'max_shops' => 5,
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '改名',
            'max_shops' => 5,
        ]);
    }

    public function test_admin_cannot_update_plan(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $plan = $this->createPlan(['code' => 'NO_EDIT']);

        $response = $this->putJson("/api/v1/system/plans/{$plan->id}", [
            'name' => '尝试改',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_unused_plan(): void
    {
        $this->actingAsSuperAdmin();
        $plan = $this->createPlan(['code' => 'TO_DELETE']);

        $response = $this->deleteJson("/api/v1/system/plans/{$plan->id}");

        $response->assertOk();
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_plan_in_use(): void
    {
        $this->actingAsSuperAdmin();
        $plan = $this->createPlan(['code' => 'IN_USE']);

        // 创建一个引用该 plan 的 tenant
        Tenant::create([
            'name' => '用了套餐的租户',
            'code' => 'TENANT_X',
            'status' => 1,
            'plan_id' => $plan->id,
        ]);

        $response = $this->deleteJson("/api/v1/system/plans/{$plan->id}");

        $response->assertStatus(409)
            ->assertJsonPath('code', 409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_admin_cannot_delete_plan(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $plan = $this->createPlan(['code' => 'NO_DEL']);

        $response = $this->deleteJson("/api/v1/system/plans/{$plan->id}");

        $response->assertStatus(403);
    }

    public function test_plan_seeder_creates_three_tiers(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas('plans', ['code' => 'FREE']);
        $this->assertDatabaseHas('plans', ['code' => 'PRO']);
        $this->assertDatabaseHas('plans', ['code' => 'ENTERPRISE']);

        $free = Plan::where('code', 'FREE')->first();
        $this->assertSame(0, (int) $free->price_monthly);
        $this->assertSame(1, $free->max_shops);

        $pro = Plan::where('code', 'PRO')->first();
        $this->assertSame(199, (int) $pro->price_monthly);
        $this->assertSame(3, $pro->max_shops);
        $this->assertTrue($pro->features['is_multi_lang']);

        $ent = Plan::where('code', 'ENTERPRISE')->first();
        $this->assertSame(10, $ent->max_shops);
        $this->assertTrue($ent->features['is_priority_support']);
    }

    public function test_tenant_plan_relation(): void
    {
        $plan = $this->createPlan(['code' => 'REL_P', 'name' => '关系测试']);
        $tenant = Tenant::create([
            'name' => '关系测试租户',
            'code' => 'TENANT_REL',
            'status' => 1,
            'plan_id' => $plan->id,
        ]);

        $this->assertInstanceOf(Plan::class, $tenant->fresh()->plan);
        $this->assertSame('REL_P', $tenant->fresh()->plan->code);
        $this->assertCount(1, $plan->fresh()->tenants);
    }
}
