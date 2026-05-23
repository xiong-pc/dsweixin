<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('可选：归属到具体店铺，null=租户级共享客户');
            $table->unsignedBigInteger('group_id')->nullable()->comment('客户分组（customer_groups.id）');
            $table->string('email', 100)->default('')->comment('邮箱（租户内 nullable+unique，由 partial index 处理）');
            $table->string('phone', 30)->default('')->comment('手机号（含国家码或纯本地）');
            $table->string('password', 200)->default('')->comment('hashed; 第三方登录可空');
            $table->string('name', 100)->default('')->comment('昵称 / 真实姓名');
            $table->string('avatar', 500)->default('');
            $table->tinyInteger('gender')->default(0)->comment('0=未知 1=男 2=女');
            $table->date('birthday')->nullable();
            $table->string('locale', 10)->default('zh-CN');
            $table->string('currency', 10)->default('CNY');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->default('');
            $table->timestamps();
            $table->softDeletes();

            // 邮箱 / 手机在租户内唯一（空字符串允许多条；非空时 application 层兜底）
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'shop_id', 'status']);
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
