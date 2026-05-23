<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Customer;
use App\Models\Mall\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpCustomerPassport;
use Tests\TestCase;

/**
 * 地址簿 CRUD：身份隔离 + 默认地址语义 + tenant 隔离。
 */
class CustomerAddressCrudTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpCustomerPassport;

    private int $tenantId;

    private Customer $customer;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCustomerPassport();
        $this->tenantId = $this->ensureDefaultTenant()->id;
        $this->customer = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'addr@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);
        $this->token = $this->customer->createToken('test')->accessToken;
    }

    private function authHeaders(?string $token = null): array
    {
        return [
            'X-Tenant-Id' => (string) $this->tenantId,
            'Authorization' => 'Bearer '.($token ?? $this->token),
        ];
    }

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'country_code' => 'CN',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'street' => '科技园 1 号',
            'postal_code' => '518000',
            'contact_name' => 'Tester',
            'contact_phone' => '13900000001',
        ], $overrides);
    }

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/shop/me/addresses')->assertStatus(401);
    }

    public function test_first_address_is_auto_default(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload());

        $response->assertOk()
            ->assertJsonPath('data.is_default', 1)
            ->assertJsonPath('data.country_code', 'CN');
    }

    public function test_second_address_default_remains_first_unless_marked(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']));

        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B']));

        $second->assertOk()->assertJsonPath('data.is_default', 0);
        $this->assertSame(
            1,
            CustomerAddress::where('customer_id', $this->customer->id)
                ->where('is_default', 1)
                ->count()
        );
    }

    public function test_creating_with_is_default_true_demotes_others(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');

        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B', 'is_default' => true]))
            ->json('data');

        $this->assertSame(0, (int) CustomerAddress::find($first['id'])->is_default);
        $this->assertSame(1, (int) CustomerAddress::find($second['id'])->is_default);
    }

    public function test_index_orders_default_first(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');
        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B', 'is_default' => true]))
            ->json('data');

        $list = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/shop/me/addresses')->json('data');

        $this->assertSame($second['id'], $list[0]['id']);
        $this->assertSame($first['id'], $list[1]['id']);
    }

    public function test_update_promote_to_default_demotes_others(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');
        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B']))
            ->json('data');

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/shop/me/addresses/'.$second['id'], ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', 1);

        $this->assertSame(0, (int) CustomerAddress::find($first['id'])->is_default);
    }

    public function test_update_other_fields_only(): void
    {
        $created = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/shop/me/addresses/'.$created['id'], ['label' => 'Office'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Office')
            ->assertJsonPath('data.is_default', 1);
    }

    public function test_update_cannot_demote_self_via_is_default_false(): void
    {
        $created = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload())
            ->json('data');

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/shop/me/addresses/'.$created['id'], ['is_default' => false])
            ->assertOk();

        // 仍然保持 default = 1
        $this->assertSame(1, (int) CustomerAddress::find($created['id'])->is_default);
    }

    public function test_delete_default_promotes_next(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');
        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B']))
            ->json('data');

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/shop/me/addresses/'.$first['id'])
            ->assertOk();

        $this->assertNull(CustomerAddress::find($first['id']));
        $this->assertSame(1, (int) CustomerAddress::find($second['id'])->is_default);
    }

    public function test_delete_non_default_keeps_default(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'A']))
            ->json('data');
        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'B']))
            ->json('data');

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/shop/me/addresses/'.$second['id'])
            ->assertOk();

        $this->assertSame(1, (int) CustomerAddress::find($first['id'])->is_default);
    }

    public function test_cannot_view_other_customer_address(): void
    {
        $other = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);
        $otherAddr = CustomerAddress::create([
            'customer_id' => $other->id,
            'country_code' => 'CN',
            'street' => 'foreign',
            'contact_name' => 'X',
            'is_default' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/shop/me/addresses/'.$otherAddr->id)
            ->assertStatus(403);
    }

    public function test_cannot_update_other_customer_address(): void
    {
        $other = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);
        $otherAddr = CustomerAddress::create([
            'customer_id' => $other->id,
            'country_code' => 'CN',
            'street' => 'foreign',
            'contact_name' => 'X',
            'is_default' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/shop/me/addresses/'.$otherAddr->id, ['label' => 'hack'])
            ->assertStatus(403);
    }

    public function test_index_only_returns_my_addresses(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['label' => 'Mine']));

        $other = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);
        CustomerAddress::create([
            'customer_id' => $other->id,
            'country_code' => 'CN',
            'street' => 'foreign-street',
            'contact_name' => 'X',
            'is_default' => 1,
        ]);

        $list = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/shop/me/addresses')->json('data');

        $this->assertCount(1, $list);
        $this->assertSame('Mine', $list[0]['label']);
    }

    public function test_validation_missing_required_fields(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', [])
            ->assertStatus(422);
    }

    public function test_email_validation_on_contact_email(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/shop/me/addresses', $this->addressPayload(['contact_email' => 'not-an-email']))
            ->assertStatus(422);
    }
}
