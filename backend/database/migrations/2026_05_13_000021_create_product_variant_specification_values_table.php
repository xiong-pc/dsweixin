<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_specification_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('specification_value_id');
            $table->timestamps();

            $table->unique(['product_variant_id', 'specification_value_id'], 'variant_spec_value_unique');
            $table->index('product_variant_id', 'variant_idx');
            $table->index('specification_value_id', 'spec_value_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_specification_values');
    }
};
