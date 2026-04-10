<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dicts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->default('')->comment('字典名称');
            $table->string('code', 64)->default('')->unique()->comment('字典编码');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
        });

        Schema::create('dict_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dict_id')->default(0)->comment('字典ID');
            $table->string('label', 64)->default('')->comment('标签');
            $table->string('value', 64)->default('')->comment('值');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->index('dict_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dict_items');
        Schema::dropIfExists('dicts');
    }
};
