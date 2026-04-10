<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(0)->after('id')->comment('租户ID');
            $table->unsignedBigInteger('dept_id')->default(0)->after('tenant_id')->comment('部门ID，0表示未分配');
            $table->string('username', 64)->unique()->after('dept_id')->comment('用户名');
            $table->string('nickname', 64)->default('')->after('username')->comment('昵称');
            $table->string('phone', 20)->default('')->after('email')->comment('手机号');
            $table->string('avatar', 255)->default('')->after('phone')->comment('头像');
            $table->tinyInteger('gender')->default(0)->after('avatar')->comment('性别(0:未知 1:男 2:女)');
            $table->tinyInteger('status')->default(1)->after('gender')->comment('状态(1:正常 0:禁用)');
            $table->index('tenant_id');
            $table->index('dept_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['dept_id']);
            $table->dropColumn(['tenant_id', 'dept_id', 'username', 'nickname', 'phone', 'avatar', 'gender', 'status']);
        });
    }
};
