<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('区域编码（如 EU, ASEAN, APAC, NA）');
            $table->string('name', 50)->comment('区域名称');
            $table->string('description', 255)->default('')->comment('区域描述');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });

        Schema::create('zone_countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id')->index();
            $table->unsignedBigInteger('country_id')->index();
            $table->timestamps();

            $table->unique(['zone_id', 'country_id'], 'zone_countries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_countries');
        Schema::dropIfExists('zones');
    }
};
