<?php

namespace Tests\Feature;

use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createNotice(array $attrs = []): Notice
    {
        return Notice::create(array_merge([
            'tenant_id' => 1,
            'title'     => '测试公告',
            'type'      => 1,
            'level'     => 0,
            'content'   => '公告内容',
            'status'    => 0,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/notices
    // -------------------------------------------------------

    public function test_index_returns_paginated_notices(): void
    {
        $this->createNotice();

        $response = $this->getJson('/api/v1/notices');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createNotice(['title' => '草稿公告', 'status' => 0]);
        $this->createNotice(['title' => '发布公告', 'status' => 1]);

        $response = $this->getJson('/api/v1/notices?status=1');

        $response->assertOk();
        foreach ($response->json('data.list') as $notice) {
            $this->assertEquals(1, $notice['status']);
        }
    }

    public function test_index_filters_by_type(): void
    {
        $this->createNotice(['type' => 1]);
        $this->createNotice(['type' => 2]);

        $response = $this->getJson('/api/v1/notices?type=2');

        $response->assertOk();
        foreach ($response->json('data.list') as $notice) {
            $this->assertEquals(2, $notice['type']);
        }
    }

    // -------------------------------------------------------
    // POST /api/v1/notices
    // -------------------------------------------------------

    public function test_store_creates_draft_notice(): void
    {
        $response = $this->postJson('/api/v1/notices', [
            'title'   => '新公告',
            'type'    => 1,
            'level'   => 0,
            'content' => '内容',
            'status'  => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.title', '新公告')
            ->assertJsonPath('data.status', 0);

        $this->assertDatabaseHas('notices', ['title' => '新公告', 'status' => 0]);
    }

    public function test_store_creates_published_notice_with_publish_time(): void
    {
        $response = $this->postJson('/api/v1/notices', [
            'title'  => '直接发布',
            'status' => 1,
        ]);

        $response->assertOk();
        $notice = Notice::where('title', '直接发布')->first();
        $this->assertEquals(1, $notice->status);
        $this->assertNotNull($notice->publish_time);
    }

    public function test_store_validates_required_title(): void
    {
        $response = $this->postJson('/api/v1/notices', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/notices/{id}
    // -------------------------------------------------------

    public function test_show_returns_notice_detail(): void
    {
        $notice = $this->createNotice(['title' => '详情公告']);

        $response = $this->getJson("/api/v1/notices/{$notice->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', '详情公告');
    }

    // -------------------------------------------------------
    // PUT /api/v1/notices/{id}
    // -------------------------------------------------------

    public function test_update_modifies_notice(): void
    {
        $notice = $this->createNotice();

        $response = $this->putJson("/api/v1/notices/{$notice->id}", [
            'title' => '修改后标题',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'title' => '修改后标题']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/notices/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_notice(): void
    {
        $notice = $this->createNotice();

        $response = $this->deleteJson("/api/v1/notices/{$notice->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }

    // -------------------------------------------------------
    // PATCH /api/v1/notices/{id}/publish
    // -------------------------------------------------------

    public function test_publish_changes_status_to_published(): void
    {
        $notice = $this->createNotice(['status' => 0]);

        $response = $this->patchJson("/api/v1/notices/{$notice->id}/publish");

        $response->assertOk()->assertJsonPath('code', 200);
        $notice->refresh();
        $this->assertEquals(1, $notice->status);
        $this->assertNotNull($notice->publish_time);
    }

    // -------------------------------------------------------
    // PATCH /api/v1/notices/{id}/revoke
    // -------------------------------------------------------

    public function test_revoke_changes_status_to_revoked(): void
    {
        $notice = $this->createNotice(['status' => 1]);

        $response = $this->patchJson("/api/v1/notices/{$notice->id}/revoke");

        $response->assertOk()->assertJsonPath('code', 200);
        $notice->refresh();
        $this->assertEquals(2, $notice->status);
    }
}
