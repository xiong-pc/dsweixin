<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->comment('租户ID');
            $table->string('title', 128)->default('')->comment('标题');
            $table->tinyInteger('type')->default(1)->comment('类型(1:通知 2:公告)');
            $table->tinyInteger('level')->default(0)->comment('级别(0:普通 1:重要 2:紧急)');
            $table->text('content')->nullable()->comment('内容');
            $table->unsignedBigInteger('publisher_id')->default(0)->comment('发布人ID，0表示未发布');
            $table->tinyInteger('status')->default(0)->comment('状态(0:草稿 1:已发布 2:已撤回)');
            $table->timestamp('publish_time')->nullable()->comment('发布时间');
            $table->timestamps();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
