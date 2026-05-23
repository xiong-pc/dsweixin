<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('label', 30)->default('')->comment('家 / 公司 / 自定义');
            $table->string('country_code', 5)->default('');
            $table->string('province', 100)->default('');
            $table->string('city', 100)->default('');
            $table->string('district', 100)->default('');
            $table->string('street', 500)->default('');
            $table->string('postal_code', 20)->default('');
            $table->string('contact_name', 100)->default('');
            $table->string('contact_phone', 30)->default('');
            $table->string('contact_email', 100)->default('');
            $table->tinyInteger('is_default')->default(0)->comment('0/1，每个 customer 至多一条 = 1');
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
