<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->comment('租户ID');
            $table->string('name', 64)->default('')->comment('配置名称');
            $table->string('key', 128)->default('')->comment('配置键');
            $table->text('value')->nullable()->comment('配置值');
            $table->tinyInteger('type')->default(0)->comment('类型(0:字符串 1:数字 2:布尔 3:JSON)');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
