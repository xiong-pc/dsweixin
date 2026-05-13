<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 100)->unique()->comment('SKU 全局唯一');
            $table->string('barcode', 100)->default('')->comment('条码 UPC/EAN，允许跨商家重复');

            $table->decimal('price', 12, 2)->default(0)->comment('销售价');
            $table->decimal('compare_at_price', 12, 2)->nullable()->comment('原价（划线价）');
            $table->decimal('cost', 12, 2)->nullable()->comment('成本价，不对客户展示');

            $table->decimal('weight', 10, 3)->default(0)->comment('重量');
            $table->string('weight_unit', 5)->default('g')->comment('重量单位 g/kg/oz/lb');
            $table->json('dimensions')->nullable()->comment('尺寸 JSON: {l,w,h,unit}');

            $table->integer('stock')->default(0)->comment('实际库存');
            $table->integer('reserved')->default(0)->comment('预占库存（下单未付）');
            $table->integer('low_stock_threshold')->default(0)->comment('低库存阈值');

            $table->string('image', 500)->default('')->comment('变体独立主图，空则取商品 cover_image');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
