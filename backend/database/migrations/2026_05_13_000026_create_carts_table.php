<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('NULL=租户全店通用购物车');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('登录用户 ID');
            $table->string('session_id', 64)->default('')->comment('游客 session 标识');
            $table->string('locale', 10)->default('zh-CN')->comment('购物车记录的语言上下文');
            $table->string('currency', 3)->default('CNY')->comment('购物车币种');
            $table->timestamps();

            // 同租户内 (customer + shop) 或 (session + shop) 唯一活跃购物车（运行时校验，SQLite 不支持 partial unique）
            $table->index(['tenant_id', 'shop_id', 'customer_id']);
            $table->index(['tenant_id', 'shop_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
