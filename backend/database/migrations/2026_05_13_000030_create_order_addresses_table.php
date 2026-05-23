<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type', 20)->default('shipping')->comment('shipping / billing');

            // 全部为快照字段，订单不依赖 customer 地址簿
            $table->string('country_code', 2)->comment('ISO 国家代码');
            $table->string('province', 100)->default('');
            $table->string('city', 100)->default('');
            $table->string('district', 100)->default('');
            $table->string('street', 255);
            $table->string('postal_code', 20)->default('');
            $table->string('contact_name', 100);
            $table->string('contact_phone', 30);
            $table->string('contact_email', 100)->default('');

            $table->timestamps();

            $table->unique(['order_id', 'type'], 'order_addresses_unique');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
