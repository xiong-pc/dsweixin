<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->default(0)->comment('操作用户ID');
            $table->string('username', 255)->default('')->comment('昵称');
            $table->integer('site_id')->default(0)->comment('站点id');
            $table->string('url', 255)->default('')->comment('对应url');
            $table->text('data')->nullable()->comment('传输数据');
            $table->string('ip', 255)->default('')->comment('ip地址');
            $table->string('action_name', 255)->default('')->comment('操作行为');

            $table->index(['uid', 'site_id'], 'IDX_ns_user_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
