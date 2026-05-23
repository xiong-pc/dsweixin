<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_value_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_value_id');
            $table->string('locale', 10);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['attribute_value_id', 'locale'], 'attr_value_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_translations');
    }
};
