<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('id')->comment('套餐 ID');
            $table->string('primary_domain', 255)->nullable()->after('code')->comment('主域名');
            $table->string('default_locale', 10)->default('zh-CN')->after('primary_domain')->comment('默认语言');
            $table->string('default_currency', 3)->default('CNY')->after('default_locale')->comment('默认币种');
            $table->string('industry', 50)->nullable()->after('default_currency')->comment('行业');

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'primary_domain',
                'default_locale',
                'default_currency',
                'industry',
            ]);
        });
    }
};
