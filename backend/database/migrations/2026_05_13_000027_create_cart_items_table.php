<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id')->comment('SPU 冗余便于查询');
            $table->unsignedBigInteger('variant_id')->comment('实际 SKU 关联');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id'], 'cart_items_unique');
            $table->index('cart_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
