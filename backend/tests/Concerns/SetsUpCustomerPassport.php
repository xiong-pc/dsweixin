<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Laravel\Passport\Client;

/**
 * 共享：测试环境下为 customers provider 创建 Personal Access Client（M09-PR35+）。
 *
 * 与 admin 端 AuthTest 的 setUp 行为对称，避免每个客户测试重复样板。
 */
trait SetsUpCustomerPassport
{
    protected function bootCustomerPassport(): void
    {
        // admin（默认 users provider）
        Client::create([
            'id' => Str::uuid(),
            'name' => 'Test Personal Access Client',
            'secret' => null,
            'redirect_uris' => ['http://localhost'],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);

        // customer（customers provider）
        Client::create([
            'id' => Str::uuid(),
            'name' => 'Customer Personal Access Client',
            'secret' => null,
            'provider' => 'customers',
            'redirect_uris' => ['http://localhost'],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }
}
