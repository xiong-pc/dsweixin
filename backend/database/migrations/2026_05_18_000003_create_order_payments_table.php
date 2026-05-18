<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('对应订单');
            $table->string('payment_method', 50)->comment('payment_methods.code 快照，如 stripe / wechat_jsapi');
            $table->string('transaction_id', 100)->unique()->comment('第三方流水号，全局唯一（webhook 幂等键）');
            $table->decimal('amount', 12, 2)->default(0)->comment('实际收款金额');
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 20)->default('pending')->comment('支付状态 enum');
            $table->timestamp('paid_at')->nullable()->comment('实际收款时间');
            $table->json('raw_response')->nullable()->comment('第三方原始响应/通知 JSON');
            $table->timestamps();

            $table->index('order_id');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
