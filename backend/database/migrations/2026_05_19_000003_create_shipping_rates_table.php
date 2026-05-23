<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_method_id');
            $table->unsignedBigInteger('zone_id')->comment('对应 zones.id（按收货国家定位）');
            $table->integer('weight_min')->default(0)->comment('重量下限（g，含）');
            $table->integer('weight_max')->default(0)->comment('重量上限（g，含；0 = 无上限）');
            $table->decimal('price', 12, 2)->default(0)->comment('该区间运费（订单货币）');
            $table->decimal('free_threshold', 12, 2)->default(0)->comment('订单金额满该值免运费；0 = 不免');
            $table->timestamps();

            $table->index(['shipping_method_id', 'zone_id', 'weight_min'], 'shipping_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
