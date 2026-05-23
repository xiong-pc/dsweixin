<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 租户级加价比例：所有商品按此 % 上浮，默认 0% 即不加价。
            // 例如 12.50 表示加 12.5%；负值视为 0。
            $table->decimal('price_markup_pct', 6, 2)
                ->default(0)
                ->after('default_currency')
                ->comment('租户全局加价百分比（PriceCalculator 第二段）');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('price_markup_pct');
        });
    }
};
