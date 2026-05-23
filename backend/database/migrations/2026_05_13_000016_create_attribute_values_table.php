<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->string('code', 50)->comment('值代码，如 cotton/china');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'code'], 'attr_values_unique');
            $table->index('attribute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
