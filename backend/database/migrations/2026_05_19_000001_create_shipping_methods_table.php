<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->string('code', 50)->default('')->comment('快递方式代码，租户内辅助唯一（如 standard/express/ems）');
            $table->string('carrier', 50)->default('')->comment('承运商（SF/EMS/DHL/FedEx 等，自由文本）');
            $table->integer('sort')->default(0);
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'sort']);
            $table->unique(['tenant_id', 'code'], 'shipping_methods_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
