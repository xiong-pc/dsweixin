<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dicts', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(0)->after('id')->comment('租户ID，0为系统公共字典');
            $table->index('tenant_id');
        });

        Schema::table('dict_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(0)->after('id')->comment('租户ID，0为系统公共字典项');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('dict_items', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('dicts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
