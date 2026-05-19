<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_method_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_method_id');
            $table->string('locale', 10);
            $table->string('name', 100)->comment('UI 显示名（如 普通快递 / Standard Shipping）');
            $table->string('description', 500)->default('')->comment('描述（运达时效等）');
            $table->timestamps();

            $table->unique(['shipping_method_id', 'locale'], 'sm_translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_translations');
    }
};
