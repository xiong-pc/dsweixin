<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specification_value_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specification_value_id');
            $table->string('locale', 10);
            $table->string('name', 100)->comment('翻译后的值名（如「红色」/「Red」/「赤」）');
            $table->timestamps();

            $table->unique(['specification_value_id', 'locale'], 'spec_value_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specification_value_translations');
    }
};
