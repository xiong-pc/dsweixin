<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::create([
            'id' => 1,
            'name' => '默认租户',
            'code' => 'DEFAULT',
            'status' => 1,
            'contact_name' => '管理员',
            'contact_phone' => '13800000000',
        ]);
    }
}
