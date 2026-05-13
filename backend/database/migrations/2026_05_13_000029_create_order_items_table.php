<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->comment('SPU 引用（仅供查询，订单不依赖此表后续变更）');
            $table->unsignedBigInteger('variant_id')->comment('SKU 引用');

            // 快照字段：商品改名/下架后老订单仍显示原始数据
            $table->string('sku', 100)->comment('SKU 快照');
            $table->string('name_snapshot', 255)->comment('商品名快照（按 order locale）');
            $table->string('image_snapshot', 500)->default('')->comment('图片快照');
            $table->string('spec_text_snapshot', 255)->default('')->comment('规格文本快照，如「颜色:红 / 尺码:M」');

            $table->decimal('unit_price', 12, 2)->comment('下单时单价快照（订单 currency）');
            $table->string('currency', 3)->comment('单价币种快照');
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2)->comment('行小计 = unit_price * quantity');

            $table->timestamps();

            $table->index('order_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
