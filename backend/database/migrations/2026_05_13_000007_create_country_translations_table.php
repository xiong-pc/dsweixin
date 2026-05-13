<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->comment('关联 countries.id');
            $table->string('locale', 10)->comment('语言代码（关联 languages.code）');
            $table->string('name', 100)->comment('对应语言的国家名称');
            $table->timestamps();

            $table->unique(['country_id', 'locale'], 'country_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_translations');
    }
};
