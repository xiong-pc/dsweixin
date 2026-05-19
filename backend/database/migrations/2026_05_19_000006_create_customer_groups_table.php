<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->string('code', 50)->default('')->comment('分组代码（normal/vip/wholesale 等）');
            $table->decimal('discount_rate', 5, 4)->default(1.0000)->comment('折扣率（0.9 = 9 折）');
            $table->integer('sort')->default(0);
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'code'], 'customer_groups_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
