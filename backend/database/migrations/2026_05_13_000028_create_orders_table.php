<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique()->comment('订单号全局唯一');

            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('NULL=租户全店通用');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('登录用户；游客下单则为 NULL');
            $table->string('session_id', 64)->default('')->comment('游客 session 标识');

            $table->string('status', 20)->default('pending')->comment('订单状态 enum');

            $table->string('currency', 3)->default('CNY');
            $table->decimal('exchange_rate', 18, 8)->default(1.0)->comment('下单时汇率快照（与 SPU base_currency 的比率）');

            $table->decimal('subtotal', 12, 2)->default(0)->comment('商品金额合计');
            $table->decimal('shipping_fee', 12, 2)->default(0)->comment('运费');
            $table->decimal('tax_fee', 12, 2)->default(0)->comment('税费');
            $table->decimal('discount', 12, 2)->default(0)->comment('优惠金额');
            $table->decimal('total', 12, 2)->default(0)->comment('应付总金额');

            $table->string('pay_method', 30)->default('')->comment('支付方式');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('shipping_no', 100)->default('');
            $table->string('shipping_company', 100)->default('');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->string('remark', 500)->default('');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id', 'status']);
            $table->index(['tenant_id', 'session_id', 'status']);
            $table->index(['tenant_id', 'shop_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
