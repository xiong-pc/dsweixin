<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 把"地区管理"菜单从"系统管理"目录下移到新建的"基础数据"目录下，
 * 并把组件路径从 system/area/index 改为 common/area/index。
 *
 * 配套：
 * - 前端视图 frontend/src/views/system/area/ → frontend/src/views/common/area/
 * - 后端控制器 App\Http\Controllers\Api\AreaController（保持原位，本来就不在 Api\System\）
 * - API 路由 /api/v1/areas（不在 system 前缀下）
 *
 * 幂等：重复执行不会产生重复 common 目录或重复迁移。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 1. 找当前 area 父菜单（type=2，component=system/area/index）
            $area = DB::table('menus')
                ->where('component', 'system/area/index')
                ->where('type', 2)
                ->first();

            if (! $area) {
                // 没有旧 area 菜单，可能是新装系统已直接 seed 到 common 下，跳过
                return;
            }

            // 2. 找或建 common 目录菜单
            $common = DB::table('menus')
                ->where('parent_id', 0)
                ->where('path', '/common')
                ->where('type', 1)
                ->first();

            $now = now();

            if (! $common) {
                $commonId = DB::table('menus')->insertGetId([
                    'parent_id' => 0,
                    'name' => '基础数据',
                    'type' => 1,
                    'path' => '/common',
                    'component' => 'Layout',
                    'permission' => '',
                    'icon' => 'DataLine',
                    'sort' => 2,
                    'visible' => 1,
                    'redirect' => '/common/area',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $commonId = $common->id;
            }

            // 3. 把 area 父菜单挂到 common 下，更新 component 和 sort
            DB::table('menus')
                ->where('id', $area->id)
                ->update([
                    'parent_id' => $commonId,
                    'component' => 'common/area/index',
                    'sort' => 1,
                    'updated_at' => $now,
                ]);

            // 注：area 下的按钮菜单（地区新增/编辑/删除）parent_id 仍是 area 自己 id，不动
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $system = DB::table('menus')
                ->where('parent_id', 0)
                ->where('path', '/system')
                ->where('type', 1)
                ->first();

            $common = DB::table('menus')
                ->where('parent_id', 0)
                ->where('path', '/common')
                ->where('type', 1)
                ->first();

            if (! $system || ! $common) {
                return;
            }

            $now = now();

            // 把挂在 common 下的 area 菜单挂回 system 下
            DB::table('menus')
                ->where('parent_id', $common->id)
                ->where('component', 'common/area/index')
                ->update([
                    'parent_id' => $system->id,
                    'component' => 'system/area/index',
                    'sort' => 9,
                    'updated_at' => $now,
                ]);

            // 若 common 目录已无任何子菜单，连目录一并删除
            $remaining = DB::table('menus')->where('parent_id', $common->id)->count();
            if ($remaining === 0) {
                DB::table('menus')->where('id', $common->id)->delete();
            }
        });
    }
};
