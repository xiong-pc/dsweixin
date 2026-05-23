<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 让 users.dept_id 改为 nullable，与代码意图统一为
     * "null 表示未分配部门"。
     *
     * 背景：
     * - StoreUserRequest / UpdateUserRequest 的 prepareForValidation 已将
     *   传入的 dept_id=0 转换为 null（注释明确写"未分配"）。
     * - validation 规则也使用 nullable|exists:depts,id。
     * - 但原 migration 2024_01_01_000002_modify_users_table.php 将该列定义
     *   为 unsignedBigInteger 不允许 null（default 0），导致 store/update
     *   测试触发 NOT NULL 约束失败。
     *
     * 本迁移让数据库 schema 与 Request 层意图保持一致，并把存量 dept_id=0
     * 的旧数据迁移为 null，统一语义。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('dept_id')
                ->nullable()
                ->default(null)
                ->comment('部门ID，null 表示未分配')
                ->change();
        });

        // 把旧数据中 dept_id=0 的（语义为"未分配"）统一迁移为 null
        DB::table('users')->where('dept_id', 0)->update(['dept_id' => null]);
    }

    public function down(): void
    {
        // 回滚：把 null 转回 0，再恢复 NOT NULL default(0)
        DB::table('users')->whereNull('dept_id')->update(['dept_id' => 0]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('dept_id')
                ->nullable(false)
                ->default(0)
                ->comment('部门ID，0表示未分配')
                ->change();
        });
    }
};
