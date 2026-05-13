<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('locale', 10);
            $table->string('name', 100)->comment('品牌名');
            $table->string('description', 500)->default('');
            $table->timestamps();

            $table->unique(['brand_id', 'locale'], 'brand_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_translations');
    }
};
