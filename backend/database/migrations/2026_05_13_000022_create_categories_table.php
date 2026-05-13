<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父类目 ID，0=根节点');
            $table->string('code', 50)->default('')->comment('内部代码，租户内辅助唯一');
            $table->string('cover_image', 500)->default('')->comment('类目封面图');
            $table->integer('sort')->default(0)->comment('同级排序');
            $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'parent_id', 'status']);
            $table->index(['tenant_id', 'status', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
