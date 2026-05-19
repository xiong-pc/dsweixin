<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Customer;
use App\Models\Mall\CustomerGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGroupCrudTest extends TestCase
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

    public function test_admin_can_create_group_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/customer-groups', [
            'code' => 'vip',
            'discount_rate' => 0.9,
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'VIP 会员', 'description' => '9 折'],
                ['locale' => 'en-US', 'name' => 'VIP Member'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'vip')
            ->assertJsonPath('data.discount_rate', '0.9000')
            ->assertJsonCount(2, 'data.translations');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('customer_groups', ['tenant_id' => $tenantId, 'code' => 'vip']);
        $this->assertDatabaseHas('customer_group_translations', ['locale' => 'zh-CN', 'name' => 'VIP 会员']);
    }

    public function test_code_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $this->postJson('/api/v1/mall/customer-groups', [
            'code' => 'BadCode',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ])->assertStatus(422);
    }

    public function test_discount_rate_range(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        // discount_rate > 1 不合法
        $this->postJson('/api/v1/mall/customer-groups', [
            'code' => 'over',
            'discount_rate' => 1.5,
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ])->assertStatus(422);

        // discount_rate < 0 不合法
        $this->postJson('/api/v1/mall/customer-groups', [
            'code' => 'neg',
            'discount_rate' => -0.1,
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ])->assertStatus(422);
    }

    public function test_code_unique_per_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip']);

        $response = $this->postJson('/api/v1/mall/customer-groups', [
            'code' => 'vip',
            'translations' => [['locale' => 'zh-CN', 'name' => 'V']],
        ]);

        $response->assertStatus(500);
        $this->assertSame(1, CustomerGroup::where('tenant_id', $tenantId)->where('code', 'vip')->count());
    }

    public function test_admin_lists_own_tenant_groups(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('oth');

        CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'mine']);
        CustomerGroup::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->getJson('/api/v1/mall/customer-groups');
        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('mine', $response->json('data.list.0.code'));
    }

    public function test_update_replaces_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip']);
        $group->translations()->create(['locale' => 'zh-CN', 'name' => '旧名']);

        $this->putJson("/api/v1/mall/customer-groups/{$group->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '新名'],
                ['locale' => 'en-US', 'name' => 'New'],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('customer_group_translations', ['name' => '旧名']);
        $this->assertDatabaseHas('customer_group_translations', ['locale' => 'zh-CN', 'name' => '新名']);
        $this->assertDatabaseHas('customer_group_translations', ['locale' => 'en-US', 'name' => 'New']);
    }

    public function test_cannot_delete_group_with_customers(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip']);
        Customer::create([
            'tenant_id' => $tenantId,
            'group_id' => $group->id,
            'email' => 'a@b.com',
        ]);

        $this->deleteJson("/api/v1/mall/customer-groups/{$group->id}")->assertStatus(400);
        $this->assertDatabaseHas('customer_groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_admin_can_soft_delete_empty_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'empty']);
        $group->translations()->create(['locale' => 'zh-CN', 'name' => '空组']);

        $this->deleteJson("/api/v1/mall/customer-groups/{$group->id}")->assertOk();

        $this->assertSoftDeleted('customer_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('customer_group_translations', ['customer_group_id' => $group->id]);
    }

    public function test_admin_cannot_access_other_tenant_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('oth');
        $group = CustomerGroup::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $this->getJson("/api/v1/mall/customer-groups/{$group->id}")->assertStatus(403);
        $this->putJson("/api/v1/mall/customer-groups/{$group->id}", ['status' => 0])->assertStatus(403);
        $this->deleteJson("/api/v1/mall/customer-groups/{$group->id}")->assertStatus(403);
    }

    public function test_keywords_filter_searches_code_and_translation_name(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $g1 = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip']);
        $g1->translations()->create(['locale' => 'zh-CN', 'name' => 'VIP 会员']);

        $g2 = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'wholesale']);
        $g2->translations()->create(['locale' => 'zh-CN', 'name' => '批发商']);

        $this->assertSame(1, $this->getJson('/api/v1/mall/customer-groups?keywords=VIP')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/mall/customer-groups?keywords=wholesale')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/mall/customer-groups?keywords=批发')->json('data.total'));
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/mall/customer-groups')->assertStatus(401);
    }
}
