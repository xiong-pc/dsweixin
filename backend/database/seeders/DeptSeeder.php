<?php

namespace Database\Seeders;

use App\Models\Dept;
use Illuminate\Database\Seeder;

class DeptSeeder extends Seeder
{
    public function run(): void
    {
        $root = Dept::create([
            'tenant_id' => 1, 'parent_id' => 0, 'name' => '总公司', 'sort' => 1, 'status' => 1,
        ]);

        $tech = Dept::create([
            'tenant_id' => 1, 'parent_id' => $root->id, 'name' => '技术部', 'sort' => 1, 'status' => 1,
        ]);
        Dept::create([
            'tenant_id' => 1, 'parent_id' => $tech->id, 'name' => '前端组', 'sort' => 1, 'status' => 1,
        ]);
        Dept::create([
            'tenant_id' => 1, 'parent_id' => $tech->id, 'name' => '后端组', 'sort' => 2, 'status' => 1,
        ]);

        Dept::create([
            'tenant_id' => 1, 'parent_id' => $root->id, 'name' => '市场部', 'sort' => 2, 'status' => 1,
        ]);
        Dept::create([
            'tenant_id' => 1, 'parent_id' => $root->id, 'name' => '财务部', 'sort' => 3, 'status' => 1,
        ]);
    }
}
