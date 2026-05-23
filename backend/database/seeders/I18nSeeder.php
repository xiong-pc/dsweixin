<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Database\Seeder;

class I18nSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLanguages();
        $this->seedCurrencies();
        $this->seedCountries();
    }

    private function seedLanguages(): void
    {
        $languages = [
            ['code' => 'zh-CN', 'name' => 'Chinese (Simplified)', 'native_name' => '中文（简体）', 'direction' => 'ltr', 'sort' => 10],
            ['code' => 'zh-TW', 'name' => 'Chinese (Traditional)', 'native_name' => '中文（繁體）', 'direction' => 'ltr', 'sort' => 15],
            ['code' => 'en-US', 'name' => 'English (US)', 'native_name' => 'English', 'direction' => 'ltr', 'sort' => 20],
            ['code' => 'ja-JP', 'name' => 'Japanese', 'native_name' => '日本語', 'direction' => 'ltr', 'sort' => 30],
            ['code' => 'ko-KR', 'name' => 'Korean', 'native_name' => '한국어', 'direction' => 'ltr', 'sort' => 40],
            ['code' => 'fr-FR', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'sort' => 50],
            ['code' => 'de-DE', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'sort' => 60],
            ['code' => 'es-ES', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'sort' => 70],
            ['code' => 'ar-SA', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'sort' => 80],
            ['code' => 'ru-RU', 'name' => 'Russian', 'native_name' => 'Русский', 'direction' => 'ltr', 'sort' => 90],
            ['code' => 'vi-VN', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'direction' => 'ltr', 'sort' => 100],
            ['code' => 'th-TH', 'name' => 'Thai', 'native_name' => 'ไทย', 'direction' => 'ltr', 'sort' => 110],
            ['code' => 'id-ID', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'direction' => 'ltr', 'sort' => 120],
        ];

        foreach ($languages as $lang) {
            Language::updateOrCreate(['code' => $lang['code']], $lang);
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'sort' => 10],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'sort' => 20],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'sort' => 30],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '￥', 'decimal_places' => 0, 'sort' => 40],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'sort' => 50],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩', 'decimal_places' => 0, 'sort' => 60],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimal_places' => 2, 'sort' => 70],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'decimal_places' => 2, 'sort' => 80],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2, 'sort' => 90],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimal_places' => 2, 'sort' => 100],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2, 'sort' => 110],
        ];

        foreach ($currencies as $cur) {
            Currency::updateOrCreate(['code' => $cur['code']], $cur);
        }
    }

    private function seedCountries(): void
    {
        // 简化清单：常见跨境电商目标国（含洲、货币、语言、电话区号、中英文翻译）
        $countries = [
            // 亚洲
            ['code' => 'CN', 'code3' => 'CHN', 'name' => 'China', 'continent' => 'Asia', 'phone_code' => '+86', 'currency_code' => 'CNY', 'locale' => 'zh-CN', 'sort' => 10, 'zh-CN' => '中国'],
            ['code' => 'JP', 'code3' => 'JPN', 'name' => 'Japan', 'continent' => 'Asia', 'phone_code' => '+81', 'currency_code' => 'JPY', 'locale' => 'ja-JP', 'sort' => 20, 'zh-CN' => '日本'],
            ['code' => 'KR', 'code3' => 'KOR', 'name' => 'South Korea', 'continent' => 'Asia', 'phone_code' => '+82', 'currency_code' => 'KRW', 'locale' => 'ko-KR', 'sort' => 30, 'zh-CN' => '韩国'],
            ['code' => 'SG', 'code3' => 'SGP', 'name' => 'Singapore', 'continent' => 'Asia', 'phone_code' => '+65', 'currency_code' => 'SGD', 'locale' => 'en-US', 'sort' => 40, 'zh-CN' => '新加坡'],
            ['code' => 'HK', 'code3' => 'HKG', 'name' => 'Hong Kong', 'continent' => 'Asia', 'phone_code' => '+852', 'currency_code' => 'HKD', 'locale' => 'zh-TW', 'sort' => 50, 'zh-CN' => '中国香港'],
            ['code' => 'TH', 'code3' => 'THA', 'name' => 'Thailand', 'continent' => 'Asia', 'phone_code' => '+66', 'currency_code' => 'CNY', 'locale' => 'th-TH', 'sort' => 60, 'zh-CN' => '泰国'],
            ['code' => 'VN', 'code3' => 'VNM', 'name' => 'Vietnam', 'continent' => 'Asia', 'phone_code' => '+84', 'currency_code' => 'CNY', 'locale' => 'vi-VN', 'sort' => 70, 'zh-CN' => '越南'],
            ['code' => 'ID', 'code3' => 'IDN', 'name' => 'Indonesia', 'continent' => 'Asia', 'phone_code' => '+62', 'currency_code' => 'CNY', 'locale' => 'id-ID', 'sort' => 80, 'zh-CN' => '印度尼西亚'],
            ['code' => 'IN', 'code3' => 'IND', 'name' => 'India', 'continent' => 'Asia', 'phone_code' => '+91', 'currency_code' => 'INR', 'locale' => 'en-US', 'sort' => 90, 'zh-CN' => '印度'],
            ['code' => 'MY', 'code3' => 'MYS', 'name' => 'Malaysia', 'continent' => 'Asia', 'phone_code' => '+60', 'currency_code' => 'USD', 'locale' => 'en-US', 'sort' => 100, 'zh-CN' => '马来西亚'],
            ['code' => 'AE', 'code3' => 'ARE', 'name' => 'United Arab Emirates', 'continent' => 'Asia', 'phone_code' => '+971', 'currency_code' => 'USD', 'locale' => 'ar-SA', 'sort' => 110, 'zh-CN' => '阿联酋'],
            ['code' => 'SA', 'code3' => 'SAU', 'name' => 'Saudi Arabia', 'continent' => 'Asia', 'phone_code' => '+966', 'currency_code' => 'USD', 'locale' => 'ar-SA', 'sort' => 120, 'zh-CN' => '沙特阿拉伯'],

            // 欧洲
            ['code' => 'GB', 'code3' => 'GBR', 'name' => 'United Kingdom', 'continent' => 'Europe', 'phone_code' => '+44', 'currency_code' => 'GBP', 'locale' => 'en-US', 'sort' => 200, 'zh-CN' => '英国'],
            ['code' => 'DE', 'code3' => 'DEU', 'name' => 'Germany', 'continent' => 'Europe', 'phone_code' => '+49', 'currency_code' => 'EUR', 'locale' => 'de-DE', 'sort' => 210, 'zh-CN' => '德国'],
            ['code' => 'FR', 'code3' => 'FRA', 'name' => 'France', 'continent' => 'Europe', 'phone_code' => '+33', 'currency_code' => 'EUR', 'locale' => 'fr-FR', 'sort' => 220, 'zh-CN' => '法国'],
            ['code' => 'ES', 'code3' => 'ESP', 'name' => 'Spain', 'continent' => 'Europe', 'phone_code' => '+34', 'currency_code' => 'EUR', 'locale' => 'es-ES', 'sort' => 230, 'zh-CN' => '西班牙'],
            ['code' => 'IT', 'code3' => 'ITA', 'name' => 'Italy', 'continent' => 'Europe', 'phone_code' => '+39', 'currency_code' => 'EUR', 'locale' => 'en-US', 'sort' => 240, 'zh-CN' => '意大利'],
            ['code' => 'NL', 'code3' => 'NLD', 'name' => 'Netherlands', 'continent' => 'Europe', 'phone_code' => '+31', 'currency_code' => 'EUR', 'locale' => 'en-US', 'sort' => 250, 'zh-CN' => '荷兰'],
            ['code' => 'RU', 'code3' => 'RUS', 'name' => 'Russia', 'continent' => 'Europe', 'phone_code' => '+7', 'currency_code' => 'USD', 'locale' => 'ru-RU', 'sort' => 260, 'zh-CN' => '俄罗斯'],

            // 美洲
            ['code' => 'US', 'code3' => 'USA', 'name' => 'United States', 'continent' => 'Americas', 'phone_code' => '+1', 'currency_code' => 'USD', 'locale' => 'en-US', 'sort' => 300, 'zh-CN' => '美国'],
            ['code' => 'CA', 'code3' => 'CAN', 'name' => 'Canada', 'continent' => 'Americas', 'phone_code' => '+1', 'currency_code' => 'CAD', 'locale' => 'en-US', 'sort' => 310, 'zh-CN' => '加拿大'],
            ['code' => 'MX', 'code3' => 'MEX', 'name' => 'Mexico', 'continent' => 'Americas', 'phone_code' => '+52', 'currency_code' => 'USD', 'locale' => 'es-ES', 'sort' => 320, 'zh-CN' => '墨西哥'],
            ['code' => 'BR', 'code3' => 'BRA', 'name' => 'Brazil', 'continent' => 'Americas', 'phone_code' => '+55', 'currency_code' => 'USD', 'locale' => 'es-ES', 'sort' => 330, 'zh-CN' => '巴西'],

            // 大洋洲
            ['code' => 'AU', 'code3' => 'AUS', 'name' => 'Australia', 'continent' => 'Oceania', 'phone_code' => '+61', 'currency_code' => 'AUD', 'locale' => 'en-US', 'sort' => 400, 'zh-CN' => '澳大利亚'],
            ['code' => 'NZ', 'code3' => 'NZL', 'name' => 'New Zealand', 'continent' => 'Oceania', 'phone_code' => '+64', 'currency_code' => 'AUD', 'locale' => 'en-US', 'sort' => 410, 'zh-CN' => '新西兰'],

            // 非洲
            ['code' => 'ZA', 'code3' => 'ZAF', 'name' => 'South Africa', 'continent' => 'Africa', 'phone_code' => '+27', 'currency_code' => 'USD', 'locale' => 'en-US', 'sort' => 500, 'zh-CN' => '南非'],
            ['code' => 'EG', 'code3' => 'EGY', 'name' => 'Egypt', 'continent' => 'Africa', 'phone_code' => '+20', 'currency_code' => 'USD', 'locale' => 'ar-SA', 'sort' => 510, 'zh-CN' => '埃及'],
            ['code' => 'NG', 'code3' => 'NGA', 'name' => 'Nigeria', 'continent' => 'Africa', 'phone_code' => '+234', 'currency_code' => 'USD', 'locale' => 'en-US', 'sort' => 520, 'zh-CN' => '尼日利亚'],
        ];

        foreach ($countries as $row) {
            $zhTrans = $row['zh-CN'] ?? null;
            unset($row['zh-CN']);

            $country = Country::updateOrCreate(['code' => $row['code']], $row);

            if ($zhTrans) {
                CountryTranslation::updateOrCreate(
                    ['country_id' => $country->id, 'locale' => 'zh-CN'],
                    ['name' => $zhTrans]
                );
            }

            // 英文翻译用 name 自身
            CountryTranslation::updateOrCreate(
                ['country_id' => $country->id, 'locale' => 'en-US'],
                ['name' => $country->name]
            );
        }
    }
}
