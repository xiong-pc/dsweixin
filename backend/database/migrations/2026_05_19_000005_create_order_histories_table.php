<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('from_status', 20)->comment('转移前状态（OrderStatus value）');
            $table->string('to_status', 20)->comment('转移后状态');
            $table->string('operator_type', 20)->default('system')->comment('user/system/event/customer');
            $table->unsignedBigInteger('operator_id')->default(0)->comment('操作人 id（user/customer），system=0');
            $table->string('reason', 255)->default('')->comment('转移原因（可选）');
            $table->string('note', 500)->default('')->comment('备注');
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
