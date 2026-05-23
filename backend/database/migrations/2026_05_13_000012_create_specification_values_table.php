<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specification_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specification_id');
            $table->string('code', 50)->comment('值代码，如 red / m-size');
            $table->string('color_hex', 7)->default('')->comment('颜色 hex，如 #FF0000，非颜色规格留空');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['specification_id', 'code'], 'spec_values_unique');
            $table->index('specification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specification_values');
    }
};
