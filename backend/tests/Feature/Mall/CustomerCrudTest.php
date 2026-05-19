<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Customer;
use App\Models\Mall\CustomerAddress;
use App\Models\Mall\CustomerGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin 后台客户管理：list / show / update / destroy。
 *
 * 客户自助注册 / 登录由 M09-PR35 处理，这里仅测后台运营。
 */
class CustomerCrudTest extends TestCase
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

    private function makeCustomer(int $tenantId, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $tenantId,
            'email' => 'u'.uniqid().'@example.com',
            'phone' => '139'.random_int(10000000, 99999999),
            'password' => 'pwd-'.uniqid(),
            'name' => 'Test User',
        ], $overrides));
    }

    public function test_admin_lists_own_tenant_customers(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('oth');

        $this->makeCustomer($tenantId);
        $this->makeCustomer($tenantId);
        $this->makeCustomer($other->id); // 不应出现

        $response = $this->getJson('/api/v1/mall/customers');
        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_sees_all_tenants(): void
    {
        $this->actingAsSuperAdmin();
        $t1 = $this->createTenant('t1');
        $t2 = $this->createTenant('t2');

        $this->makeCustomer($t1->id);
        $this->makeCustomer($t2->id);
        $this->makeCustomer($t2->id);

        $response = $this->getJson('/api/v1/mall/customers');
        $this->assertSame(3, $response->json('data.total'));
    }

    public function test_show_returns_customer_with_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip', 'discount_rate' => 0.9]);
        $group->translations()->create(['locale' => 'zh-CN', 'name' => 'VIP']);

        $customer = $this->makeCustomer($tenantId, ['group_id' => $group->id]);

        $response = $this->getJson("/api/v1/mall/customers/{$customer->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.group.code', 'vip')
            ->assertJsonPath('data.group.translations.0.name', 'VIP');
    }

    public function test_admin_can_disable_customer(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $customer = $this->makeCustomer($tenantId, ['status' => 1]);

        $response = $this->putJson("/api/v1/mall/customers/{$customer->id}", [
            'status' => 0,
        ]);

        $response->assertOk();
        $this->assertSame(0, (int) $customer->fresh()->status);
    }

    public function test_admin_can_change_customer_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip', 'discount_rate' => 0.9]);
        $customer = $this->makeCustomer($tenantId);

        $response = $this->putJson("/api/v1/mall/customers/{$customer->id}", [
            'group_id' => $group->id,
        ]);

        $response->assertOk();
        $this->assertSame($group->id, (int) $customer->fresh()->group_id);
    }

    public function test_cannot_assign_other_tenant_group(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $other = $this->createTenant('oth');
        $foreignGroup = CustomerGroup::create(['tenant_id' => $other->id, 'code' => 'vip']);
        $customer = $this->makeCustomer($tenantId);

        $response = $this->putJson("/api/v1/mall/customers/{$customer->id}", [
            'group_id' => $foreignGroup->id,
        ]);

        $response->assertStatus(400);
        $this->assertNull($customer->fresh()->group_id);
    }

    public function test_admin_cannot_change_email_or_phone_or_password(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $customer = $this->makeCustomer($tenantId);
        $originalEmail = $customer->email;
        $originalPhone = $customer->phone;

        // service 层只允许 name/status/group_id/locale/currency，其他字段静默忽略
        $this->putJson("/api/v1/mall/customers/{$customer->id}", [
            'email' => 'hacker@evil.com',
            'phone' => '99999999999',
            'password' => 'pwned',
        ])->assertOk();

        $fresh = $customer->fresh();
        $this->assertSame($originalEmail, $fresh->email);
        $this->assertSame($originalPhone, $fresh->phone);
    }

    public function test_admin_cannot_access_other_tenant_customer(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $other = $this->createTenant('oth');
        $foreign = $this->makeCustomer($other->id);

        $this->getJson("/api/v1/mall/customers/{$foreign->id}")->assertStatus(403);
        $this->putJson("/api/v1/mall/customers/{$foreign->id}", ['status' => 0])->assertStatus(403);
        $this->deleteJson("/api/v1/mall/customers/{$foreign->id}")->assertStatus(403);
    }

    public function test_admin_can_soft_delete_customer(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $customer = $this->makeCustomer($tenantId);

        $this->deleteJson("/api/v1/mall/customers/{$customer->id}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_keywords_filter_searches_email_phone_name(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $this->makeCustomer($tenantId, ['email' => 'alice@example.com', 'name' => 'Alice']);
        $this->makeCustomer($tenantId, ['email' => 'bob@example.com', 'name' => 'Bob']);
        $this->makeCustomer($tenantId, ['phone' => '13900001234', 'name' => 'Carl']);

        $this->assertSame(1, $this->getJson('/api/v1/mall/customers?keywords=alice')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/mall/customers?keywords=Bob')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/mall/customers?keywords=13900001234')->json('data.total'));
    }

    public function test_filter_by_group_and_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $group = CustomerGroup::create(['tenant_id' => $tenantId, 'code' => 'vip']);

        $this->makeCustomer($tenantId, ['group_id' => $group->id]);
        $this->makeCustomer($tenantId, ['group_id' => $group->id, 'status' => 0]);
        $this->makeCustomer($tenantId);

        $this->assertSame(2, $this->getJson("/api/v1/mall/customers?group_id={$group->id}")->json('data.total'));
        $this->assertSame(1, $this->getJson("/api/v1/mall/customers?group_id={$group->id}&status=0")->json('data.total'));
    }

    public function test_password_is_hidden_in_response(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $customer = $this->makeCustomer($tenantId, ['password' => 'plaintext-pwd']);

        $response = $this->getJson("/api/v1/mall/customers/{$customer->id}");
        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_password_is_auto_hashed(): void
    {
        $this->ensureDefaultTenant();
        $tenantId = (int) Tenant::query()->first()->id;
        $customer = $this->makeCustomer($tenantId, ['password' => 'plaintext-pwd']);

        // 模型 casts 含 'password' => 'hashed'
        $this->assertNotSame('plaintext-pwd', $customer->fresh()->getAttributes()['password']);
        $this->assertTrue(password_verify('plaintext-pwd', $customer->fresh()->getAttributes()['password']));
    }

    public function test_addresses_relation_orders_default_first(): void
    {
        $this->ensureDefaultTenant();
        $tenantId = (int) Tenant::query()->first()->id;
        $customer = $this->makeCustomer($tenantId);

        CustomerAddress::create([
            'customer_id' => $customer->id, 'is_default' => 0, 'street' => 'A',
            'contact_name' => 'Alice', 'contact_phone' => '1', 'country_code' => 'CN',
        ]);
        $defaultAddr = CustomerAddress::create([
            'customer_id' => $customer->id, 'is_default' => 1, 'street' => 'B',
            'contact_name' => 'Bob', 'contact_phone' => '2', 'country_code' => 'CN',
        ]);

        $first = $customer->fresh()->addresses->first();
        $this->assertSame($defaultAddr->id, $first?->id);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/mall/customers')->assertStatus(401);
    }
}
