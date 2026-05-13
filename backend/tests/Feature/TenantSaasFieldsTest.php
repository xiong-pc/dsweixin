<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantSaasFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_zh_cn_when_not_specified(): void
    {
        $tenant = Tenant::create([
            'name' => 'Locale 默认测试',
            'code' => 'LOCALE_DEFAULT',
            'status' => 1,
        ]);

        $this->assertSame('zh-CN', $tenant->fresh()->default_locale);
    }

    public function test_default_currency_is_cny_when_not_specified(): void
    {
        $tenant = Tenant::create([
            'name' => 'Currency 默认测试',
            'code' => 'CURRENCY_DEFAULT',
            'status' => 1,
        ]);

        $this->assertSame('CNY', $tenant->fresh()->default_currency);
    }

    public function test_can_create_tenant_with_all_saas_fields(): void
    {
        $tenant = Tenant::create([
            'name' => '完整 SaaS 字段',
            'code' => 'FULL_SAAS',
            'status' => 1,
            'plan_id' => 99,
            'primary_domain' => 'acme.com',
            'default_locale' => 'en-US',
            'default_currency' => 'USD',
            'industry' => 'fashion',
        ]);

        $fresh = $tenant->fresh();

        $this->assertSame(99, $fresh->plan_id);
        $this->assertSame('acme.com', $fresh->primary_domain);
        $this->assertSame('en-US', $fresh->default_locale);
        $this->assertSame('USD', $fresh->default_currency);
        $this->assertSame('fashion', $fresh->industry);
    }

    public function test_can_update_saas_fields_on_existing_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => '待升级租户',
            'code' => 'UPGRADE_T',
            'status' => 1,
        ]);

        $tenant->update([
            'plan_id' => 2,
            'primary_domain' => 'upgraded.com',
            'default_locale' => 'ja-JP',
            'default_currency' => 'JPY',
            'industry' => 'electronics',
        ]);

        $fresh = $tenant->fresh();

        $this->assertSame(2, $fresh->plan_id);
        $this->assertSame('upgraded.com', $fresh->primary_domain);
        $this->assertSame('ja-JP', $fresh->default_locale);
        $this->assertSame('JPY', $fresh->default_currency);
        $this->assertSame('electronics', $fresh->industry);
    }

    public function test_plan_id_is_cast_to_integer(): void
    {
        $tenant = Tenant::create([
            'name' => 'Plan ID 类型测试',
            'code' => 'PLAN_CAST',
            'status' => 1,
            'plan_id' => '42',
        ]);

        $this->assertIsInt($tenant->fresh()->plan_id);
        $this->assertSame(42, $tenant->fresh()->plan_id);
    }

    public function test_legacy_tenant_fields_still_work(): void
    {
        $tenant = Tenant::create([
            'name' => '老字段兼容测试',
            'code' => 'LEGACY_T',
            'status' => 1,
            'contact_name' => '联系人',
            'contact_phone' => '13800000000',
            'remark' => '备注',
        ]);

        $fresh = $tenant->fresh();

        $this->assertSame('老字段兼容测试', $fresh->name);
        $this->assertSame('LEGACY_T', $fresh->code);
        $this->assertSame(1, $fresh->status);
        $this->assertSame('联系人', $fresh->contact_name);
        $this->assertSame('13800000000', $fresh->contact_phone);
        $this->assertSame('备注', $fresh->remark);
    }

    public function test_expired_at_still_casts_as_datetime(): void
    {
        $tenant = Tenant::create([
            'name' => 'expired_at 测试',
            'code' => 'EXPIRED_T',
            'status' => 1,
            'expired_at' => '2027-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(Carbon::class, $tenant->fresh()->expired_at);
    }
}
