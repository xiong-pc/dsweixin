<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('locale', 10);
            $table->string('name', 255)->comment('商品名');
            $table->string('slug', 255)->default('')->comment('URL slug，shop+locale 内唯一');
            $table->string('short_description', 500)->default('');
            $table->longText('description')->nullable()->comment('商品详情 HTML');
            $table->string('seo_title', 255)->default('');
            $table->string('seo_keywords', 500)->default('');
            $table->string('seo_description', 500)->default('');
            $table->timestamps();

            $table->unique(['product_id', 'locale'], 'product_translations_unique');
            $table->index(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
