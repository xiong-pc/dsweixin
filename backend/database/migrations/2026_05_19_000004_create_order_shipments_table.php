<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('carrier', 50)->default('')->comment('承运商（SF/EMS/DHL/...，自由文本）');
            $table->string('tracking_no', 100)->default('')->comment('运单号');
            $table->string('status', 20)->default('shipped')->comment('shipped/delivered/cancelled');
            $table->timestamp('shipped_at')->nullable()->comment('发货时间');
            $table->timestamp('delivered_at')->nullable()->comment('签收时间');
            $table->decimal('fee', 12, 2)->default(0)->comment('实际运费（订单币种）');
            $table->json('raw_response')->nullable()->comment('承运商 API 原始响应（预留对接物流面单 API）');
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('tracking_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipments');
    }
};
