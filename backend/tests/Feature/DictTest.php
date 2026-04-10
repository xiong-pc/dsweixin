<?php

namespace Tests\Feature;

use App\Models\Dict;
use App\Models\DictItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createDict(array $attrs = []): Dict
    {
        return Dict::create(array_merge([
            'name'   => '测试字典',
            'code'   => 'test_dict_' . uniqid(),
            'status' => 1,
        ], $attrs));
    }

    private function createDictItem(Dict $dict, array $attrs = []): DictItem
    {
        return DictItem::create(array_merge([
            'dict_id' => $dict->id,
            'label'   => '标签',
            'value'   => 'val_' . uniqid(),
            'sort'    => 0,
            'status'  => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/dicts
    // -------------------------------------------------------

    public function test_index_returns_paginated_dicts(): void
    {
        $this->createDict();

        $response = $this->getJson('/api/v1/dicts');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createDict(['name' => '独特字典名', 'code' => 'unique_dict']);
        $this->createDict(['name' => '普通字典']);

        $response = $this->getJson('/api/v1/dicts?keywords=独特');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('独特字典名', $list[0]['name']);
    }

    // -------------------------------------------------------
    // POST /api/v1/dicts
    // -------------------------------------------------------

    public function test_store_creates_dict(): void
    {
        $response = $this->postJson('/api/v1/dicts', [
            'name'   => '新字典',
            'code'   => 'new_dict',
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.code', 'new_dict');

        $this->assertDatabaseHas('dicts', ['code' => 'new_dict']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/dicts', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_unique_code(): void
    {
        $this->createDict(['code' => 'dup_dict']);

        $response = $this->postJson('/api/v1/dicts', [
            'name' => '重复',
            'code' => 'dup_dict',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/dicts/{id}
    // -------------------------------------------------------

    public function test_show_returns_dict_with_items(): void
    {
        $dict = $this->createDict();
        $this->createDictItem($dict, ['label' => '选项A', 'value' => 'a']);

        $response = $this->getJson("/api/v1/dicts/{$dict->id}");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.items'));
    }

    // -------------------------------------------------------
    // PUT /api/v1/dicts/{id}
    // -------------------------------------------------------

    public function test_update_modifies_dict(): void
    {
        $dict = $this->createDict();

        $response = $this->putJson("/api/v1/dicts/{$dict->id}", [
            'name' => '修改后名称',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('dicts', ['id' => $dict->id, 'name' => '修改后名称']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/dicts/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_dict_and_items(): void
    {
        $dict = $this->createDict();
        $item = $this->createDictItem($dict);

        $response = $this->deleteJson("/api/v1/dicts/{$dict->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('dicts', ['id' => $dict->id]);
        $this->assertDatabaseMissing('dict_items', ['id' => $item->id]);
    }

    // -------------------------------------------------------
    // GET /api/v1/dicts/{id}/items
    // -------------------------------------------------------

    public function test_items_returns_dict_items(): void
    {
        $dict = $this->createDict();
        $this->createDictItem($dict, ['label' => '男', 'value' => '1']);
        $this->createDictItem($dict, ['label' => '女', 'value' => '2']);

        $response = $this->getJson("/api/v1/dicts/{$dict->id}/items");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------
    // 字典项 CRUD
    // -------------------------------------------------------

    public function test_dict_item_store_creates_item(): void
    {
        $dict = $this->createDict();

        $response = $this->postJson('/api/v1/dict-items', [
            'dict_id' => $dict->id,
            'label'   => '新标签',
            'value'   => 'new_val',
            'sort'    => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('dict_items', ['dict_id' => $dict->id, 'label' => '新标签']);
    }

    public function test_dict_item_store_validates_dict_exists(): void
    {
        $response = $this->postJson('/api/v1/dict-items', [
            'dict_id' => 99999,
            'label'   => '标签',
            'value'   => 'val',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_dict_item_update_modifies_item(): void
    {
        $dict = $this->createDict();
        $item = $this->createDictItem($dict);

        $response = $this->putJson("/api/v1/dict-items/{$item->id}", [
            'label' => '修改标签',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('dict_items', ['id' => $item->id, 'label' => '修改标签']);
    }

    public function test_dict_item_destroy_deletes_item(): void
    {
        $dict = $this->createDict();
        $item = $this->createDictItem($dict);

        $response = $this->deleteJson("/api/v1/dict-items/{$item->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('dict_items', ['id' => $item->id]);
    }
}
