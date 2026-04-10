<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            // 0 表示系统级公共菜单（超级管理员管理），非 0 表示租户自定义菜单
            $table->unsignedBigInteger('tenant_id')->default(0)->after('id')->comment('租户ID，0为系统公共菜单');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
