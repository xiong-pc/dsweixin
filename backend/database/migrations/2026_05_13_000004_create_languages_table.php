<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('BCP-47 语言代码（如 zh-CN, en-US, ja-JP）');
            $table->string('name', 50)->comment('英文名称（如 English, Japanese）');
            $table->string('native_name', 50)->default('')->comment('本地名称（如 中文, 日本語）');
            $table->string('direction', 3)->default('ltr')->comment('文字方向：ltr 或 rtl');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
