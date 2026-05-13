<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Currency;
use App\Models\Language;
use Database\Seeders\I18nSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class I18nBaseTest extends TestCase
{
    use RefreshDatabase;

    // ============ Languages ============

    public function test_admin_can_list_languages(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Language::create(['code' => 'zh-CN', 'name' => 'Chinese', 'native_name' => '中文']);
        Language::create(['code' => 'en-US', 'name' => 'English']);

        $response = $this->getJson('/api/v1/system/languages');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total']]);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_create_language(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/languages', [
            'code' => 'ja-JP',
            'name' => 'Japanese',
            'native_name' => '日本語',
            'direction' => 'ltr',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'ja-JP')
            ->assertJsonPath('data.direction', 'ltr');
        $this->assertDatabaseHas('languages', ['code' => 'ja-JP']);
    }

    public function test_admin_cannot_create_language(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/languages', [
            'code' => 'fr-FR',
            'name' => 'French',
        ]);

        $response->assertStatus(403);
    }

    public function test_language_code_must_be_unique(): void
    {
        Language::create(['code' => 'zh-CN', 'name' => 'Chinese']);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/languages', [
            'code' => 'zh-CN',
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function test_language_code_format_validation(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/languages', [
            'code' => 'invalid',
            'name' => 'Invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_super_admin_can_update_language(): void
    {
        $this->actingAsSuperAdmin();
        $lang = Language::create(['code' => 'en-US', 'name' => 'English']);

        $response = $this->putJson("/api/v1/system/languages/{$lang->id}", [
            'native_name' => 'English (Updated)',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('languages', [
            'id' => $lang->id,
            'native_name' => 'English (Updated)',
        ]);
    }

    public function test_super_admin_can_delete_language(): void
    {
        $this->actingAsSuperAdmin();
        $lang = Language::create(['code' => 'fr-FR', 'name' => 'French']);

        $response = $this->deleteJson("/api/v1/system/languages/{$lang->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('languages', ['id' => $lang->id]);
    }

    // ============ Currencies ============

    public function test_admin_can_list_currencies(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Currency::create(['code' => 'CNY', 'name' => 'CNY', 'symbol' => '¥']);
        Currency::create(['code' => 'USD', 'name' => 'USD', 'symbol' => '$']);

        $response = $this->getJson('/api/v1/system/currencies');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_create_currency(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/currencies', [
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '￥',
            'decimal_places' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'JPY')
            ->assertJsonPath('data.decimal_places', 0);
    }

    public function test_currency_code_must_be_3_uppercase(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/currencies', [
            'code' => 'us',
            'name' => 'US Dollar',
        ]);

        $response->assertStatus(422);
    }

    public function test_currency_code_must_be_unique(): void
    {
        Currency::create(['code' => 'EUR', 'name' => 'Euro']);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/currencies', [
            'code' => 'EUR',
            'name' => 'Duplicate Euro',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_create_currency(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/currencies', [
            'code' => 'AUD',
            'name' => 'AUD',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_currency_decimal_places(): void
    {
        $this->actingAsSuperAdmin();
        $cur = Currency::create(['code' => 'KRW', 'name' => 'Won']);

        $response = $this->putJson("/api/v1/system/currencies/{$cur->id}", [
            'decimal_places' => 0,
        ]);

        $response->assertOk();
        $this->assertSame(0, $cur->fresh()->decimal_places);
    }

    // ============ Countries ============

    public function test_admin_can_list_countries(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Country::create(['code' => 'CN', 'code3' => 'CHN', 'name' => 'China', 'continent' => 'Asia']);
        Country::create(['code' => 'US', 'code3' => 'USA', 'name' => 'United States', 'continent' => 'Americas']);

        $response = $this->getJson('/api/v1/system/countries');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_create_country_with_translations(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/countries', [
            'code' => 'JP',
            'code3' => 'JPN',
            'name' => 'Japan',
            'continent' => 'Asia',
            'phone_code' => '+81',
            'currency_code' => 'JPY',
            'locale' => 'ja-JP',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '日本'],
                ['locale' => 'en-US', 'name' => 'Japan'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'JP')
            ->assertJsonPath('data.currency_code', 'JPY');

        $this->assertDatabaseHas('countries', ['code' => 'JP', 'code3' => 'JPN']);
        $this->assertDatabaseHas('country_translations', ['locale' => 'zh-CN', 'name' => '日本']);
        $this->assertDatabaseHas('country_translations', ['locale' => 'en-US', 'name' => 'Japan']);
    }

    public function test_country_code_must_be_2_uppercase(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/countries', [
            'code' => 'cn',
            'name' => 'China',
        ]);

        $response->assertStatus(422);
    }

    public function test_country_code_must_be_unique(): void
    {
        Country::create(['code' => 'CN', 'name' => 'China']);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/countries', [
            'code' => 'CN',
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_filter_countries_by_continent(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Country::create(['code' => 'CN', 'name' => 'China', 'continent' => 'Asia']);
        Country::create(['code' => 'JP', 'name' => 'Japan', 'continent' => 'Asia']);
        Country::create(['code' => 'US', 'name' => 'USA', 'continent' => 'Americas']);

        $response = $this->getJson('/api/v1/system/countries?continent=Asia');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_update_country_translations(): void
    {
        $this->actingAsSuperAdmin();
        $country = Country::create(['code' => 'JP', 'name' => 'Japan']);
        CountryTranslation::create(['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '日本（旧）']);

        $response = $this->putJson("/api/v1/system/countries/{$country->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '日本'],
                ['locale' => 'ja-JP', 'name' => '日本'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('country_translations', ['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '日本']);
        $this->assertDatabaseHas('country_translations', ['country_id' => $country->id, 'locale' => 'ja-JP', 'name' => '日本']);
        $this->assertDatabaseMissing('country_translations', ['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '日本（旧）']);
    }

    public function test_country_translations_unique_per_locale(): void
    {
        $country = Country::create(['code' => 'CN', 'name' => 'China']);
        CountryTranslation::create(['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '中国']);

        $this->expectException(QueryException::class);
        CountryTranslation::create(['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => 'Duplicate']);
    }

    public function test_get_translation_method_returns_locale_name(): void
    {
        $country = Country::create(['code' => 'CN', 'name' => 'China']);
        CountryTranslation::create(['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '中国']);

        $this->assertSame('中国', $country->fresh()->getTranslation('zh-CN'));
        // 找不到 fallback 到默认 name
        $this->assertSame('China', $country->fresh()->getTranslation('ko-KR'));
    }

    public function test_super_admin_can_delete_country_and_translations(): void
    {
        $this->actingAsSuperAdmin();
        $country = Country::create(['code' => 'TH', 'name' => 'Thailand']);
        CountryTranslation::create(['country_id' => $country->id, 'locale' => 'zh-CN', 'name' => '泰国']);

        $response = $this->deleteJson("/api/v1/system/countries/{$country->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
        $this->assertDatabaseMissing('country_translations', ['country_id' => $country->id]);
    }

    // ============ I18nSeeder ============

    public function test_i18n_seeder_seeds_all_three_tables(): void
    {
        $this->seed(I18nSeeder::class);

        $this->assertGreaterThanOrEqual(12, Language::count());
        $this->assertGreaterThanOrEqual(11, Currency::count());
        $this->assertGreaterThanOrEqual(28, Country::count());

        // 关键数据点检查
        $this->assertDatabaseHas('languages', ['code' => 'zh-CN', 'direction' => 'ltr']);
        $this->assertDatabaseHas('languages', ['code' => 'ar-SA', 'direction' => 'rtl']);
        $this->assertDatabaseHas('currencies', ['code' => 'JPY', 'decimal_places' => 0]);
        $this->assertDatabaseHas('currencies', ['code' => 'CNY', 'decimal_places' => 2]);
        $this->assertDatabaseHas('countries', ['code' => 'CN', 'currency_code' => 'CNY', 'locale' => 'zh-CN']);
        $this->assertDatabaseHas('country_translations', ['locale' => 'zh-CN', 'name' => '日本']);
    }

    public function test_unauthenticated_cannot_access_i18n_resources(): void
    {
        $this->getJson('/api/v1/system/languages')->assertStatus(401);
        $this->getJson('/api/v1/system/currencies')->assertStatus(401);
        $this->getJson('/api/v1/system/countries')->assertStatus(401);
    }
}
