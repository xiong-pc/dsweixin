<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_group_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_group_id');
            $table->string('locale', 10);
            $table->string('name', 100);
            $table->string('description', 500)->default('');
            $table->timestamps();

            $table->unique(['customer_group_id', 'locale'], 'cg_translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_translations');
    }
};
