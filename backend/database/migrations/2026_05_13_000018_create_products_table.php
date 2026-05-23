<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('NULL 表示租户内全店铺共享');
            $table->unsignedBigInteger('brand_id')->nullable()->comment('品牌，M04-PR16 后建表');
            $table->unsignedBigInteger('category_id')->nullable()->comment('类目，M04-PR15 后建表');
            $table->string('sku_prefix', 50)->default('')->comment('SKU 编码前缀，租户内辅助唯一');
            $table->string('cover_image', 500)->default('')->comment('主图 URL');
            $table->json('images')->nullable()->comment('图集 JSON 数组');
            $table->decimal('base_price', 12, 2)->default(0)->comment('SPU 起步价（用于列表展示），实际价以 SKU 为准');
            $table->string('base_currency', 3)->default('CNY')->comment('SPU 起步价对应币种');
            $table->tinyInteger('status')->default(0)->comment('0=草稿 1=上架');
            $table->integer('sort')->default(0);
            $table->integer('sold_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'brand_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
