<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3)->comment('源币种 ISO 4217');
            $table->string('to_currency', 3)->comment('目标币种 ISO 4217');
            $table->decimal('rate', 18, 8)->comment('汇率（from → to 的乘数）');
            $table->string('source', 50)->default('manual')->comment('数据源：manual / exchangerate-api / openexchangerates');
            $table->timestamp('fetched_at')->nullable()->comment('数据获取时间');
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency'], 'exchange_rates_pair_unique');
            $table->index(['from_currency', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
