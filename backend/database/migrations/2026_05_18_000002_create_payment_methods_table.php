<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('NULL=租户级共用，否则限定 shop');
            $table->string('code', 50)->comment('租户内对外 code，如 stripe / wechat_jsapi / wechat_h5');
            $table->string('driver', 50)->comment('驱动 code，对应 config/payment.php drivers 键');
            $table->string('name', 100)->default('')->comment('展示名（如「微信支付（H5）」）');
            $table->json('config')->nullable()->comment('驱动私有配置：merchant_id/secret/sandbox 等');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort')->default(0)->comment('展示排序');
            $table->timestamps();
            $table->softDeletes();

            // SQLite 不支持 partial unique on nullable shop_id，code 唯一性靠 service 层校验
            $table->index(['tenant_id', 'shop_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
