<?php

namespace Database\Seeders;

use App\Models\Dict;
use App\Models\DictItem;
use Illuminate\Database\Seeder;

class DictSeeder extends Seeder
{
    public function run(): void
    {
        $gender = Dict::create(['name' => '性别', 'code' => 'gender', 'status' => 1]);
        DictItem::create(['dict_id' => $gender->id, 'label' => '未知', 'value' => '0', 'sort' => 1]);
        DictItem::create(['dict_id' => $gender->id, 'label' => '男', 'value' => '1', 'sort' => 2]);
        DictItem::create(['dict_id' => $gender->id, 'label' => '女', 'value' => '2', 'sort' => 3]);

        $status = Dict::create(['name' => '状态', 'code' => 'status', 'status' => 1]);
        DictItem::create(['dict_id' => $status->id, 'label' => '正常', 'value' => '1', 'sort' => 1]);
        DictItem::create(['dict_id' => $status->id, 'label' => '禁用', 'value' => '0', 'sort' => 2]);

        $noticeType = Dict::create(['name' => '通知类型', 'code' => 'notice_type', 'status' => 1]);
        DictItem::create(['dict_id' => $noticeType->id, 'label' => '通知', 'value' => '1', 'sort' => 1]);
        DictItem::create(['dict_id' => $noticeType->id, 'label' => '公告', 'value' => '2', 'sort' => 2]);

        $noticeLevel = Dict::create(['name' => '通知级别', 'code' => 'notice_level', 'status' => 1]);
        DictItem::create(['dict_id' => $noticeLevel->id, 'label' => '普通', 'value' => '0', 'sort' => 1]);
        DictItem::create(['dict_id' => $noticeLevel->id, 'label' => '重要', 'value' => '1', 'sort' => 2]);
        DictItem::create(['dict_id' => $noticeLevel->id, 'label' => '紧急', 'value' => '2', 'sort' => 3]);

        $menuType = Dict::create(['name' => '菜单类型', 'code' => 'menu_type', 'status' => 1]);
        DictItem::create(['dict_id' => $menuType->id, 'label' => '目录', 'value' => '1', 'sort' => 1]);
        DictItem::create(['dict_id' => $menuType->id, 'label' => '菜单', 'value' => '2', 'sort' => 2]);
        DictItem::create(['dict_id' => $menuType->id, 'label' => '按钮', 'value' => '3', 'sort' => 3]);
        DictItem::create(['dict_id' => $menuType->id, 'label' => '外链', 'value' => '4', 'sort' => 4]);

        $dataScope = Dict::create(['name' => '数据权限', 'code' => 'data_scope', 'status' => 1]);
        DictItem::create(['dict_id' => $dataScope->id, 'label' => '全部数据', 'value' => '0', 'sort' => 1]);
        DictItem::create(['dict_id' => $dataScope->id, 'label' => '本部门数据', 'value' => '1', 'sort' => 2]);
        DictItem::create(['dict_id' => $dataScope->id, 'label' => '本部门及子部门', 'value' => '2', 'sort' => 3]);
        DictItem::create(['dict_id' => $dataScope->id, 'label' => '仅本人数据', 'value' => '3', 'sort' => 4]);
        DictItem::create(['dict_id' => $dataScope->id, 'label' => '自定义', 'value' => '4', 'sort' => 5]);
    }
}
