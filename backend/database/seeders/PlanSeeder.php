<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'FREE',
                'name' => '免费版',
                'description' => '适合个人卖家试用，1 个店铺，仅基础功能',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'billing_period' => 'forever',
                'trial_days' => 0,
                'max_shops' => 1,
                'max_products' => 50,
                'max_orders_per_month' => 100,
                'max_users' => 2,
                'max_storage_mb' => 256,
                'max_languages' => 1,
                'max_currencies' => 1,
                'features' => [
                    'is_custom_domain' => false,
                    'is_api_access' => false,
                    'is_multi_lang' => false,
                ],
                'status' => 1,
                'sort' => 10,
            ],
            [
                'code' => 'PRO',
                'name' => '专业版',
                'description' => '适合中小型跨境卖家，多店多语言',
                'price_monthly' => 199.00,
                'price_yearly' => 1990.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_shops' => 3,
                'max_products' => 500,
                'max_orders_per_month' => 5000,
                'max_users' => 10,
                'max_storage_mb' => 5120,
                'max_languages' => 3,
                'max_currencies' => 3,
                'features' => [
                    'is_custom_domain' => true,
                    'is_api_access' => true,
                    'is_multi_lang' => true,
                ],
                'status' => 1,
                'sort' => 20,
            ],
            [
                'code' => 'ENTERPRISE',
                'name' => '企业版',
                'description' => '面向大型企业的全功能套餐',
                'price_monthly' => 999.00,
                'price_yearly' => 9990.00,
                'billing_period' => 'monthly',
                'trial_days' => 30,
                'max_shops' => 10,
                'max_products' => 5000,
                'max_orders_per_month' => 50000,
                'max_users' => 50,
                'max_storage_mb' => 51200,
                'max_languages' => 10,
                'max_currencies' => 10,
                'features' => [
                    'is_custom_domain' => true,
                    'is_api_access' => true,
                    'is_multi_lang' => true,
                    'is_priority_support' => true,
                    'is_sla_99_9' => true,
                ],
                'status' => 1,
                'sort' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
