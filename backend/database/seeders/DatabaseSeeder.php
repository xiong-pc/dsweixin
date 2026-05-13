<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            TenantSeeder::class,
            MenuSeeder::class,
            RoleSeeder::class,
            DeptSeeder::class,
            UserSeeder::class,
            DictSeeder::class,
        ]);
    }
}
