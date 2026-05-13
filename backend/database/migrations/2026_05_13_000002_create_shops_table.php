<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index()->comment('所属租户');
            $table->string('name', 50)->default('')->comment('店铺名称');
            $table->string('code', 30)->default('')->comment('店铺编码（租户内唯一）');
            $table->string('subdomain', 64)->nullable()->unique()->comment('子域名（全平台唯一）');
            $table->string('locale', 10)->default('zh-CN')->comment('主语言');
            $table->string('currency', 3)->default('CNY')->comment('主币种');
            $table->string('timezone', 64)->default('Asia/Shanghai')->comment('时区');
            $table->unsignedBigInteger('theme_id')->nullable()->comment('主题 ID');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->integer('sort')->default(0)->comment('排序');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'shops_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
