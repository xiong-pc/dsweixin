<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->string('code', 50)->comment('内部代码，如 material/origin');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'attrs_tenant_code_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
