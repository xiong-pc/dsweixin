<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pid')->default(0)->comment('父级');
            $table->string('name', 50)->default('')->comment('名称');
            $table->string('shortname', 30)->default('')->comment('简称');
            $table->string('longitude', 30)->default('')->comment('经度');
            $table->string('latitude', 30)->default('')->comment('纬度');
            $table->smallInteger('level')->default(0)->comment('级别');
            $table->mediumInteger('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态1有效');

            $table->index(['name', 'shortname'], 'IDX_nc_area');
            $table->index(['level', 'sort', 'status']);
            $table->index(['longitude', 'latitude']);
            $table->index('pid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
