<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->default('')->comment('租户名称');
            $table->string('code', 30)->default('')->unique()->comment('租户编码');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->string('contact_name', 50)->default('')->comment('联系人');
            $table->string('contact_phone', 20)->default('')->comment('联系电话');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
