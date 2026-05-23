<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Category;
use App\Models\Mall\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $code): Tenant
    {
        return Tenant::create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => 1,
            'primary_domain' => "{$code}.example.com",
        ]);
    }

    public function test_admin_can_create_category_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/categories', [
            'code' => 'apparel',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '服装', 'description' => '服装类目'],
                ['locale' => 'en-US', 'name' => 'Apparel'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'apparel')
            ->assertJsonPath('data.parent_id', 0)
            ->assertJsonCount(2, 'data.translations');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('categories', ['tenant_id' => $tenantId, 'code' => 'apparel']);
        $this->assertDatabaseHas('category_translations', ['locale' => 'zh-CN', 'name' => '服装', 'description' => '服装类目']);
    }

    public function test_index_returns_tree_structure(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $root = Category::create(['tenant_id' => $tenantId, 'code' => 'root', 'sort' => 1]);
        $child = Category::create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'code' => 'child']);
        $grandchild = Category::create(['tenant_id' => $tenantId, 'parent_id' => $child->id, 'code' => 'gc']);

        $response = $this->getJson('/api/v1/mall/categories');

        $response->assertOk();
        $tree = $response->json('data');
        $this->assertCount(1, $tree);
        $this->assertSame('root', $tree[0]['code']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('child', $tree[0]['children'][0]['code']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
        $this->assertSame('gc', $tree[0]['children'][0]['children'][0]['code']);
    }

    public function test_create_child_category_with_parent(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $root = Category::create(['tenant_id' => $tenantId, 'code' => 'root']);

        $response = $this->postJson('/api/v1/mall/categories', [
            'parent_id' => $root->id,
            'code' => 'sub',
            'translations' => [['locale' => 'zh-CN', 'name' => '子类目']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.parent_id', $root->id);
    }

    public function test_create_with_invalid_parent_returns_error(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/categories', [
            'parent_id' => 99999,
            'code' => 'orphan',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_use_other_tenant_category_as_parent(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $other = $this->createTenant('other');
        $otherRoot = Category::create(['tenant_id' => $other->id, 'code' => 'other-root']);

        $response = $this->postJson('/api/v1/mall/categories', [
            'parent_id' => $otherRoot->id,
            'code' => 'cross',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertStatus(400);
    }

    public function test_admin_can_update_category_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $cat = Category::create(['tenant_id' => $tenantId, 'code' => 'electronics']);
        $cat->translations()->create(['locale' => 'zh-CN', 'name' => '旧名']);

        $response = $this->putJson("/api/v1/mall/categories/{$cat->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '电子产品'],
                ['locale' => 'en-US', 'name' => 'Electronics'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('category_translations', ['locale' => 'zh-CN', 'name' => '电子产品']);
        $this->assertDatabaseMissing('category_translations', ['locale' => 'zh-CN', 'name' => '旧名']);
    }

    public function test_cannot_set_parent_to_self(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $cat = Category::create(['tenant_id' => $tenantId, 'code' => 'self']);

        $response = $this->putJson("/api/v1/mall/categories/{$cat->id}", [
            'parent_id' => $cat->id,
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_set_parent_to_descendant_creating_cycle(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $root = Category::create(['tenant_id' => $tenantId, 'code' => 'root']);
        $child = Category::create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'code' => 'child']);
        $grandchild = Category::create(['tenant_id' => $tenantId, 'parent_id' => $child->id, 'code' => 'gc']);

        // 把 root 的 parent 设为 grandchild —— 应失败（grandchild 是 root 的后代）
        $response = $this->putJson("/api/v1/mall/categories/{$root->id}", [
            'parent_id' => $grandchild->id,
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $root = Category::create(['tenant_id' => $tenantId, 'code' => 'root']);
        Category::create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'code' => 'child']);

        $response = $this->deleteJson("/api/v1/mall/categories/{$root->id}");

        $response->assertStatus(400);
    }

    public function test_cannot_delete_category_with_associated_products(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $cat = Category::create(['tenant_id' => $tenantId, 'code' => 'apparel']);
        Product::create(['tenant_id' => $tenantId, 'category_id' => $cat->id]);

        $response = $this->deleteJson("/api/v1/mall/categories/{$cat->id}");

        $response->assertStatus(400);
    }

    public function test_admin_can_delete_leaf_category(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $cat = Category::create(['tenant_id' => $tenantId, 'code' => 'leaf']);
        $cat->translations()->create(['locale' => 'zh-CN', 'name' => '叶子']);

        $response = $this->deleteJson("/api/v1/mall/categories/{$cat->id}");

        $response->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $cat->id]);
        $this->assertDatabaseMissing('category_translations', ['category_id' => $cat->id]);
    }

    public function test_admin_cannot_access_other_tenant_categories(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other');

        Category::create(['tenant_id' => $myTenantId, 'code' => 'mine']);
        Category::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->getJson('/api/v1/mall/categories');
        $response->assertOk();
        $tree = $response->json('data');
        $this->assertCount(1, $tree);
        $this->assertSame('mine', $tree[0]['code']);
    }

    public function test_admin_cannot_update_other_tenant_category(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $cat = Category::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->putJson("/api/v1/mall/categories/{$cat->id}", [
            'sort' => 99,
        ]);

        $response->assertStatus(403);
    }

    public function test_reorder_updates_parent_and_sort_in_batch(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $a = Category::create(['tenant_id' => $tenantId, 'code' => 'a', 'sort' => 1]);
        $b = Category::create(['tenant_id' => $tenantId, 'code' => 'b', 'sort' => 2]);
        $c = Category::create(['tenant_id' => $tenantId, 'parent_id' => $a->id, 'code' => 'c']);

        // 把 c 移到 b 下，并调换 a/b 排序
        $response = $this->postJson('/api/v1/mall/categories/reorder', [
            'items' => [
                ['id' => $c->id, 'parent_id' => $b->id, 'sort' => 1],
                ['id' => $a->id, 'sort' => 99],
                ['id' => $b->id, 'sort' => 1],
            ],
        ]);

        $response->assertOk();
        $this->assertSame($b->id, (int) $c->fresh()->parent_id);
        $this->assertSame(99, $a->fresh()->sort);
        $this->assertSame(1, $b->fresh()->sort);
    }

    public function test_reorder_rejects_cycle(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $root = Category::create(['tenant_id' => $tenantId, 'code' => 'root']);
        $child = Category::create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'code' => 'child']);

        // 试图通过 reorder 创建循环：把 root 的 parent 设为 child
        $response = $this->postJson('/api/v1/mall/categories/reorder', [
            'items' => [
                ['id' => $root->id, 'parent_id' => $child->id],
            ],
        ]);

        $response->assertStatus(400);
    }

    public function test_unauthenticated_cannot_access_categories(): void
    {
        $this->getJson('/api/v1/mall/categories')->assertStatus(401);
    }

    public function test_code_format_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/categories', [
            'code' => 'BadCode',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertStatus(422);
    }

    public function test_has_translations_trait_works_on_category_model(): void
    {
        // 验证 trait 在 Category 模型上正常工作（trait 第三次复用确认）
        $tenantId = 1;
        $cat = Category::create(['tenant_id' => $tenantId, 'code' => 'test']);
        $cat->setTranslations([
            ['locale' => 'zh-CN', 'name' => '测试'],
            ['locale' => 'en-US', 'name' => 'Test'],
        ]);

        $this->assertSame('测试', $cat->fresh()->getTranslation('zh-CN')->name);
        $this->assertSame('Test', $cat->fresh()->getTranslation('en-US')->name);
        $this->assertSame('Test', $cat->fresh()->getTranslation('ko-KR', 'en-US')->name);
    }
}
