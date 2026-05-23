<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离，规格组归属租户');
            $table->string('code', 50)->comment('内部代码，如 color/size，租户内唯一');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'specs_tenant_code_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specifications');
    }
};
