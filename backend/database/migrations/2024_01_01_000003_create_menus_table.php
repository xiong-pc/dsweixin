<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('name', 64)->default('')->comment('菜单名称');
            $table->tinyInteger('type')->default(0)->comment('菜单类型(1:目录 2:菜单 3:按钮 4:外链)');
            $table->string('path', 128)->default('')->comment('路由路径');
            $table->string('component', 128)->default('')->comment('组件路径');
            $table->string('permission', 128)->default('')->comment('权限标识');
            $table->string('icon', 64)->default('')->comment('图标');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('visible')->default(1)->comment('是否可见(1:是 0:否)');
            $table->string('redirect', 128)->default('')->comment('跳转路径');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
