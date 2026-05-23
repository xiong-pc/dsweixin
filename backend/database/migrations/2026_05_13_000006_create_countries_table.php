<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique()->comment('ISO 3166-1 alpha-2（如 CN, US, JP）');
            $table->string('code3', 3)->default('')->comment('ISO 3166-1 alpha-3（如 CHN, USA, JPN）');
            $table->string('name', 100)->comment('英文名称');
            $table->string('continent', 20)->default('')->comment('所属洲：Asia/Europe/Americas/Africa/Oceania');
            $table->string('phone_code', 10)->default('')->comment('国际电话区号（如 +86, +1, +81）');
            $table->string('currency_code', 3)->default('')->comment('默认币种代码');
            $table->string('locale', 10)->default('')->comment('默认语言代码');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
