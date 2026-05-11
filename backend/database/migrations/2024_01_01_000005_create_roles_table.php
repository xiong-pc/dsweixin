<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->comment('租户ID');
            $table->string('name', 64)->default('')->comment('角色名称');
            $table->string('code', 64)->default('')->unique()->comment('角色编码');
            $table->tinyInteger('data_scope')->default(0)->comment('数据权限(0:全部 1:本部门 2:本部门及子部门 3:仅本人 4:自定义)');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->index('tenant_id');
        });

        Schema::create('role_menus', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('menu_id');
            $table->primary(['role_id', 'menu_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('role_depts', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('dept_id');
            $table->primary(['role_id', 'dept_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_depts');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_menus');
        Schema::dropIfExists('roles');
    }
};
