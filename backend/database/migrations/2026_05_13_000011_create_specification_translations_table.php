<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specification_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specification_id');
            $table->string('locale', 10);
            $table->string('name', 100)->comment('翻译后的规格名（如「颜色」/「Color」/「色」）');
            $table->timestamps();

            $table->unique(['specification_id', 'locale'], 'spec_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specification_translations');
    }
};
