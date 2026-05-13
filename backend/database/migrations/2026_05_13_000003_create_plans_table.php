<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->default('')->comment('套餐名称');
            $table->string('code', 30)->unique()->comment('套餐编码（如 FREE / PRO / ENTERPRISE）');
            $table->string('description', 255)->default('')->comment('套餐描述');

            $table->decimal('price_monthly', 10, 2)->default(0)->comment('月付价格');
            $table->decimal('price_yearly', 10, 2)->default(0)->comment('年付价格');
            $table->string('currency', 3)->default('CNY')->comment('币种');
            $table->string('billing_period', 20)->default('monthly')->comment('计费周期：monthly/yearly/forever');
            $table->integer('trial_days')->default(0)->comment('试用天数');

            // 核心额度
            $table->integer('max_shops')->default(1)->comment('店铺数上限');
            $table->integer('max_products')->default(100)->comment('商品数上限');
            $table->integer('max_orders_per_month')->default(1000)->comment('月订单数上限');
            $table->integer('max_users')->default(5)->comment('用户数上限');
            $table->integer('max_storage_mb')->default(1024)->comment('存储上限（MB）');
            $table->integer('max_languages')->default(1)->comment('语言数上限');
            $table->integer('max_currencies')->default(1)->comment('币种数上限');

            $table->json('features')->nullable()->comment('附加功能开关（is_custom_domain/is_api_access 等）');

            $table->tinyInteger('status')->default(1)->comment('状态(1:启用 0:停用)');
            $table->integer('sort')->default(0)->comment('排序');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
