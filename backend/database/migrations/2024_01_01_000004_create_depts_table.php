<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->comment('租户ID');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('name', 64)->comment('部门名称');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->timestamps();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depts');
    }
};
