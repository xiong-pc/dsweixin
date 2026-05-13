<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique()->comment('ISO 4217 币种代码（如 CNY, USD, EUR）');
            $table->string('name', 50)->comment('英文名称');
            $table->string('symbol', 10)->default('')->comment('货币符号（如 ¥, $, €）');
            $table->tinyInteger('decimal_places')->default(2)->comment('小数位数（JPY/KRW 为 0）');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
