<?php

namespace Tests\Unit;

use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasTranslationsTraitTest extends TestCase
{
    use RefreshDatabase;

    private function createSpec(): Specification
    {
        return Specification::create(['tenant_id' => 1, 'code' => 'color']);
    }

    public function test_translations_relation_returns_translations(): void
    {
        $spec = $this->createSpec();
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '颜色']);
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'en-US', 'name' => 'Color']);

        $this->assertCount(2, $spec->fresh()->translations);
    }

    public function test_get_translation_returns_for_specific_locale(): void
    {
        $spec = $this->createSpec();
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '颜色']);
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'ja-JP', 'name' => '色']);

        $trans = $spec->fresh()->getTranslation('ja-JP');
        $this->assertNotNull($trans);
        $this->assertSame('色', $trans->name);
    }

    public function test_get_translation_returns_null_when_no_match_and_no_fallback(): void
    {
        $spec = $this->createSpec();
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '颜色']);

        $this->assertNull($spec->fresh()->getTranslation('ko-KR'));
    }

    public function test_get_translation_uses_fallback_when_locale_missing(): void
    {
        $spec = $this->createSpec();
        SpecificationTranslation::create(['specification_id' => $spec->id, 'locale' => 'en-US', 'name' => 'Color']);

        $trans = $spec->fresh()->getTranslation('ko-KR', 'en-US');
        $this->assertNotNull($trans);
        $this->assertSame('Color', $trans->name);
    }

    public function test_get_translated_name_falls_back_to_code_when_no_translations(): void
    {
        $spec = $this->createSpec();

        // 没有任何翻译，应回退到 code
        $this->assertSame('color', $spec->fresh()->getTranslatedName('zh-CN'));
    }

    public function test_set_translations_creates_translations_incrementally(): void
    {
        $spec = $this->createSpec();

        $spec->setTranslations([
            ['locale' => 'zh-CN', 'name' => '颜色'],
            ['locale' => 'en-US', 'name' => 'Color'],
        ]);

        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '颜色']);
        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'en-US', 'name' => 'Color']);

        // 二次调用更新已有 locale
        $spec->setTranslations([
            ['locale' => 'zh-CN', 'name' => '色彩'],
            ['locale' => 'ja-JP', 'name' => '色'],
        ]);

        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'zh-CN', 'name' => '色彩']);
        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'en-US', 'name' => 'Color']); // 保留
        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'ja-JP', 'name' => '色']);
    }

    public function test_set_translations_skips_empty_entries(): void
    {
        $spec = $this->createSpec();

        $spec->setTranslations([
            ['locale' => 'zh-CN', 'name' => '颜色'],
            ['locale' => '', 'name' => 'BadLocale'],
            ['locale' => 'en-US', 'name' => ''],
        ]);

        $this->assertSame(1, $spec->fresh()->translations()->count());
    }

    public function test_sync_translations_replaces_all(): void
    {
        $spec = $this->createSpec();
        $spec->setTranslations([
            ['locale' => 'zh-CN', 'name' => '颜色'],
            ['locale' => 'en-US', 'name' => 'Color'],
        ]);

        $spec->syncTranslations([
            ['locale' => 'ja-JP', 'name' => '色'],
        ]);

        $this->assertDatabaseMissing('specification_translations', ['specification_id' => $spec->id, 'locale' => 'zh-CN']);
        $this->assertDatabaseMissing('specification_translations', ['specification_id' => $spec->id, 'locale' => 'en-US']);
        $this->assertDatabaseHas('specification_translations', ['specification_id' => $spec->id, 'locale' => 'ja-JP']);
        $this->assertSame(1, $spec->fresh()->translations()->count());
    }
}
