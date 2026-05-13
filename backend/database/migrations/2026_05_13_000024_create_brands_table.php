<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->string('code', 50)->default('')->comment('品牌代码，租户内辅助唯一');
            $table->string('logo', 500)->default('')->comment('品牌 Logo URL');
            $table->string('website', 500)->default('')->comment('官网 URL');
            $table->integer('sort')->default(0);
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'sort']);
            $table->index(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
