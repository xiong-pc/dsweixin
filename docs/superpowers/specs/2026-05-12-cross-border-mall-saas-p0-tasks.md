# 跨境电商 SaaS · P0 MVP 详细任务清单

> 配套文档：[`2026-05-12-cross-border-mall-saas-plan.md`](./2026-05-12-cross-border-mall-saas-plan.md)
>
> 本文档将 P0 MVP（W1-W6）拆成 **PR 级别可执行任务**：每个 PR 独立可合并，
> 含明确文件清单、测试要求、验收标准、上下游依赖。

---

## 0. 总览

| | 数据 |
|---|---|
| 模块数 | 11（M01-M11）|
| PR 数 | 47 |
| 工日 | 47 工日（≈ 9 周单人 / 5-6 周双人）|
| 关键路径 | M01 → M02 → M03 → M05 → M06 → M11 |
| 完成标准 | 一个测试租户能从注册→建商品→消费者下单→支付成功→后台发货全链路跑通，且 130+150 个老测试全部通过 |

### PR 命名规范

```
feat(mall): M01-PR1 tenants 表扩字段（plan_id、locale、currency 等）
feat(mall): M03-PR10 products 主表 + ProductTranslation
test(mall): M05-PR20 库存预占机制 Feature test
chore(mall): M11-PR42 frontend-shop Nuxt 3 工程脚手架
```

格式：`<type>(mall): <module>-PR<num> <一句话>`

### 分支策略

```
main
└── feat/mall-mvp                  # P0 主开发分支（长存活）
    ├── feat/mall-m01-saas-layer   # 每个模块一个分支
    ├── feat/mall-m02-i18n-base
    ├── ...
    └── feat/mall-m11-shop-nuxt
```

每个模块完成后 `feat/mall-m0X-*` 合入 `feat/mall-mvp`，P0 完整后整体合入 `main`。

---

## Sprint 0 — 工程准备（0.5 工日）

| PR | 标题 | 操作 |
|---|---|---|
| **PR0.1** | 拉 `feat/mall-mvp` 主开发分支 | `git checkout -b feat/mall-mvp main && git push -u origin feat/mall-mvp` |
| **PR0.2** | CI workflow 加 mall 标签 | 给 mall 相关测试打 `@group mall`，可单独跑 `php artisan test --group=mall` |
| **PR0.3** | 在 `tech-debt.md` 增加 P0 进度区块 | 跟踪进度，每完成一个 PR 在表格里标 ✅ |

---

## M01 — SaaS 层改造（3 工日 · 4 PR）

> 把现状的"租户+商户"模型升级为"租户+店铺"，扩展 SaaS 必备字段（套餐、域名、locale、currency 等）。

### M01-PR1 · tenants 表扩字段（0.5d）

**目的**：tenants 表增加 SaaS 必需字段，不破坏现有租户。

**文件**：
- `backend/database/migrations/2026_05_xx_add_saas_fields_to_tenants.php`
- `backend/app/Models/Tenant.php`（模型 fillable + cast）
- `backend/database/seeders/TenantSeeder.php`（更新默认租户填新字段）

**Migration 字段**（5 个新字段；现有 `expired_at` 复用作"套餐到期"，不重复）：
```php
$table->bigInteger('plan_id')->nullable()->index()->after('id')->comment('套餐 ID');
$table->string('primary_domain', 255)->nullable()->after('code')->comment('主域名 acme.com');
$table->string('default_locale', 10)->default('zh-CN')->after('primary_domain');
$table->string('default_currency', 3)->default('CNY')->after('default_locale');
$table->string('industry', 50)->nullable()->after('default_currency')->comment('行业');
// expired_at 复用现有字段，语义升级为"套餐到期时间"
```

**测试**：
- `tests/Feature/System/TenantSaasFieldsTest.php`：迁移后老租户能正常读取，新字段默认值正确
- 老 130 个 test 全过

**依赖**：无（M01 起点）

---

### M01-PR2 · merchants → shops 改造（1d）

**目的**：把"商户"概念改造为"店铺（语言站点）"，加 locale、currency、subdomain 字段。

**文件**：
- `backend/database/migrations/2026_05_xx_rename_merchants_to_shops.php`
- `backend/database/migrations/2026_05_xx_add_shop_localization_fields.php`
- `backend/app/Models/System/Shop.php`（新增，从 `Merchant.php` 迁移并改名）
- `backend/app/Models/System/Merchant.php`（**删除**）
- `backend/app/Http/Controllers/Api/System/ShopController.php`（从 `MerchantController` 改名）
- `backend/app/Http/Resources/Api/System/ShopResource.php`
- `backend/app/Http/Requests/Api/System/Shop*Request.php`
- 全代码库 grep 替换：`merchant` → `shop`、`Merchant` → `Shop`、`merchant_id` → `shop_id`

**Migration 字段**：
```php
// 改名表
Schema::rename('merchants', 'shops');
// 加字段
$table->string('locale', 10)->after('name')->default('zh-CN');
$table->string('currency', 3)->after('locale')->default('CNY');
$table->string('subdomain', 64)->nullable()->unique()->after('currency')->comment('子域名 acme-cn');
$table->string('timezone', 64)->default('Asia/Shanghai')->after('subdomain');
$table->bigInteger('theme_id')->nullable()->after('timezone');
```

**测试**：
- `tests/Feature/System/ShopCrudTest.php`：从 `MerchantCrudTest.php` 改名 + 加新字段断言
- `tests/Feature/System/ShopSubdomainUniqueTest.php`：subdomain 全平台唯一约束
- 整库 grep 验证无 `merchant` 残留：`grep -r "merchant" backend/app backend/routes backend/tests | grep -v vendor` 应空

**依赖**：M01-PR1（tenants 字段就位）

**风险**：现有 `dept_id` / RBAC / Tenant middleware 中所有 `merchant_id` 引用需同步替换。

---

### M01-PR3 · TenantMiddleware 升级支持 host 解析（0.5d）

**目的**：消费者前台访问 `acme-cn.dsweixin.com` 时，自动识别 shop 并注入 `tenant_id` + `shop_id` + `locale` 到请求上下文。

**文件**：
- `backend/app/Http/Middleware/TenantMiddleware.php`（升级）
- `backend/app/Http/Middleware/ShopHostMiddleware.php`（新增 · 前台专用）
- `backend/bootstrap/app.php`（注册 middleware alias）
- `backend/config/cors.php`（允许 `*.dsweixin.com` + 自定义域名）

**逻辑**：
```php
// ShopHostMiddleware 大致逻辑
$host = $request->getHost();             // acme-cn.dsweixin.com
$subdomain = explode('.', $host)[0];     // acme-cn
$shop = Shop::where('subdomain', $subdomain)->first();
if (!$shop || $shop->status !== 1) abort(404);
app()->instance('current_shop', $shop);
app()->instance('current_tenant', $shop->tenant);
app()->setLocale($shop->locale);
```

**测试**：
- `tests/Feature/Shop/ShopHostResolutionTest.php`：
  - host=acme-cn.dsweixin.com → 注入 acme-cn shop
  - host=unknown.dsweixin.com → 404
  - host=admin.dsweixin.com → 不走 ShopHostMiddleware（后台）

**依赖**：M01-PR2

---

### M01-PR4 · plans 表 + 套餐管理（1d）

**目的**：SaaS 套餐分级（免费版 / 标准版 / 企业版），租户绑定套餐限制额度。

**文件**：
- `backend/database/migrations/2026_05_xx_create_plans_table.php`
- `backend/app/Models/System/Plan.php`
- `backend/app/Http/Controllers/Api/System/PlanController.php`
- `backend/app/Http/Resources/Api/System/PlanResource.php`
- `backend/app/Services/Api/System/PlanService.php`
- `backend/database/seeders/PlanSeeder.php`（3 个默认套餐）
- `frontend/src/views/system/plan/index.vue`（仅平台超管能看）
- `frontend/src/api/system/plan.ts`

**Migration 字段**：
```php
id, name, code(free/standard/enterprise),
max_shops, max_products, max_orders_per_month,
monthly_price, yearly_price, currency,
features(json), status, sort, timestamps
```

**测试**：
- `tests/Feature/System/PlanCrudTest.php`：CRUD + 平台超管权限
- `tests/Feature/System/TenantPlanQuotaTest.php`：tenant 创建第 N+1 个 shop 时报"超出套餐限制"

**依赖**：M01-PR1

---

## M02 — 全球化基础数据（2 工日 · 3 PR）

> 多语言、多币种、国家、区域分组——所有跨境业务的基石。

### M02-PR5 · languages + currencies + countries（1d）

**目的**：维护可选语言/币种/国家清单 + 国家翻译。

**文件**：
- `backend/database/migrations/2026_05_xx_create_languages_table.php`
- `backend/database/migrations/2026_05_xx_create_currencies_table.php`
- `backend/database/migrations/2026_05_xx_create_countries_table.php`
- `backend/database/migrations/2026_05_xx_create_country_translations_table.php`
- `backend/app/Models/Mall/Language.php`
- `backend/app/Models/Mall/Currency.php`
- `backend/app/Models/Mall/Country.php`
- `backend/app/Models/Mall/CountryTranslation.php`
- `backend/database/seeders/MallI18nSeeder.php`（默认 10 语言、20 币种、249 国家）
- `backend/app/Http/Controllers/Api/Mall/LanguageController.php`
- 类似 Currency/Country controller、resource、request

**字段（核心）**：
```php
// languages: code(zh-CN), name, native_name, flag, rtl(bool), status
// currencies: code(USD), symbol, name, decimal_places(2), status
// countries: code(US), iso3(USA), phone_code(1), default_currency, continent, status
// country_translations: country_id, locale, name
```

**测试**：
- `tests/Feature/Mall/LanguageCrudTest.php`
- `tests/Feature/Mall/CurrencyCrudTest.php`
- `tests/Feature/Mall/CountryTranslationsTest.php`：locale=zh-CN 时返回中文名；locale=en-US 返回英文名

**依赖**：M01 完成

---

### M02-PR6 · zones 区域分组（0.5d）

**目的**：把多个国家归到一个 zone（如"东南亚"、"欧盟"），用于运费/税费分组。

**文件**：
- `backend/database/migrations/2026_05_xx_create_zones_table.php`
- `backend/database/migrations/2026_05_xx_create_zone_countries_table.php`
- `backend/app/Models/Mall/Zone.php`
- `backend/app/Http/Controllers/Api/Mall/ZoneController.php`
- `frontend/src/views/mall/zone/index.vue`（含国家多选）

**测试**：
- `tests/Feature/Mall/ZoneCrudTest.php`：CRUD + 国家关联 + 同租户隔离

**依赖**：M02-PR5

---

### M02-PR7 · exchange_rates + 定时同步 Job（0.5d）

**目的**：维护汇率，每日定时从开放 API（如 exchangerate-api.com）同步。

**文件**：
- `backend/database/migrations/2026_05_xx_create_exchange_rates_table.php`
- `backend/app/Models/Mall/ExchangeRate.php`
- `backend/app/Jobs/Mall/SyncExchangeRatesJob.php`
- `backend/app/Console/Commands/Mall/SyncExchangeRatesCommand.php`
- `backend/routes/console.php`（schedule daily）
- `backend/app/Services/Api/Mall/CurrencyConversionService.php`

**字段**：
```php
id, from_currency, to_currency, rate(decimal 18,8), 
effective_at, source(api|manual), tenant_id NULL
```

**测试**：
- `tests/Unit/Mall/CurrencyConversionTest.php`：USD→CNY 换算 + markup 倍率
- `tests/Feature/Mall/SyncExchangeRatesJobTest.php`：mock API，断言数据落库

**依赖**：M02-PR5

---

## M03 — 商品 4 层模型（7 工日 · 7 PR）

> 跨境电商最复杂的模块：SPU/SKU/规格/属性 4 层结构 + 全套翻译表 + 后台 UI。

### M03-PR8 · specifications + values + 翻译表（1d）

**目的**：规格组（颜色/尺码）+ 规格值（红/蓝/M/L）。

**文件**：
- `backend/database/migrations/2026_05_xx_create_specifications_table.php`
- `backend/database/migrations/2026_05_xx_create_specification_translations_table.php`
- `backend/database/migrations/2026_05_xx_create_specification_values_table.php`
- `backend/database/migrations/2026_05_xx_create_specification_value_translations_table.php`
- `backend/app/Models/Mall/Specification.php`
- `backend/app/Models/Mall/SpecificationTranslation.php`
- `backend/app/Models/Mall/SpecificationValue.php`
- `backend/app/Models/Mall/SpecificationValueTranslation.php`
- `backend/app/Traits/HasTranslations.php`（**通用 trait**，后续翻译表都用）
- `backend/app/Http/Controllers/Api/Mall/SpecificationController.php`

**HasTranslations trait（关键）**：
```php
trait HasTranslations {
    public function translations(): HasMany { /* */ }
    public function translation(?string $locale = null): ?Model { /* */ }
    public function getNameAttribute(): string {
        return $this->translation()?->name ?? $this->getKey();
    }
    public function setTranslations(array $translations): void { /* upsert */ }
}
```

**测试**：
- `tests/Unit/Mall/HasTranslationsTraitTest.php`：trait 单元测试（fallback / 多 locale 取值 / setTranslations）
- `tests/Feature/Mall/SpecificationCrudTest.php`：含翻译数据的 CRUD

**依赖**：M02 完成

---

### M03-PR9 · attributes + values + 翻译表（1d）

**目的**：属性组（材质/产地，非选购维度）+ 属性值。

**文件**：同 PR8 模式（4 表 + 4 模型 + 1 controller），把 specification 替换为 attribute。

**字段差异**：
- `attributes.type`：text / number / boolean / select / multiselect

**测试**：
- `tests/Feature/Mall/AttributeCrudTest.php`

**依赖**：M03-PR8（HasTranslations trait 复用）

---

### M03-PR10 · products 主表 + ProductTranslation（1.5d）

**目的**：SPU 商品主体。

**文件**：
- `backend/database/migrations/2026_05_xx_create_products_table.php`
- `backend/database/migrations/2026_05_xx_create_product_translations_table.php`
- `backend/app/Models/Mall/Product.php`
- `backend/app/Models/Mall/ProductTranslation.php`
- `backend/app/Http/Controllers/Api/Mall/ProductController.php`
- `backend/app/Http/Resources/Api/Mall/ProductResource.php`
- `backend/app/Http/Requests/Api/Mall/Product*Request.php`
- `backend/app/Services/Api/Mall/ProductService.php`

**Products 字段**：
```php
id, tenant_id, shop_id NULL,
brand_id, category_id,
sku_prefix, cover_image, images(json),
base_price(decimal 12,2), base_currency(3),
status(0|1), sort, sold_count, view_count,
timestamps, deleted_at
```

**ProductTranslation 字段**：
```php
id, product_id, locale,
name, slug, short_description, description(longText),
seo_title, seo_keywords, seo_description
```

**测试**：
- `tests/Feature/Mall/ProductCrudTest.php`：含多语言创建/更新
- `tests/Feature/Mall/ProductSlugUniqueTest.php`：同 shop+locale 下 slug 唯一
- `tests/Feature/Mall/ProductTenantIsolationTest.php`：A 租户看不到 B 租户商品

**依赖**：M03-PR8、M03-PR9

---

### M03-PR11 · product_variants + 规格关联（1d）

**目的**：SKU 商品变体（颜色 × 尺码 = 4 个 SKU）。

**文件**：
- `backend/database/migrations/2026_05_xx_create_product_variants_table.php`
- `backend/database/migrations/2026_05_xx_create_product_variant_specification_values_table.php`
- `backend/app/Models/Mall/ProductVariant.php`
- `backend/app/Http/Controllers/Api/Mall/ProductVariantController.php`
- `backend/app/Services/Api/Mall/ProductVariantService.php`

**Variants 字段**：
```php
id, product_id, sku UNIQUE, barcode,
price, compare_at_price, cost,
weight(decimal), weight_unit(g|kg|oz|lb),
dimensions(json: {l,w,h,unit}),
stock(int), reserved(int) DEFAULT 0,
low_stock_threshold, image, status, sort
```

**测试**：
- `tests/Feature/Mall/ProductVariantCrudTest.php`
- `tests/Feature/Mall/ProductVariantSpecMatrixTest.php`：颜色×尺码生成正确变体数

**依赖**：M03-PR10

---

### M03-PR12 · 后台商品列表 + 创建表单（基础信息）（1d）

**目的**：后台 Vue 界面：商品列表 + 创建/编辑（仅基础信息 + 翻译切换）。

**文件**：
- `frontend/src/views/mall/product/index.vue`（列表 + 搜索 + 筛选）
- `frontend/src/views/mall/product/edit.vue`（基础信息 + 翻译 tab）
- `frontend/src/views/mall/product/components/BasicInfo.vue`
- `frontend/src/views/mall/product/components/TranslationTabs.vue`（**通用组件**，后续类目/品牌都复用）
- `frontend/src/api/mall/product.ts`
- `frontend/src/types/mall/product.ts`

**TranslationTabs.vue 设计**：
```vue
<el-tabs v-model="activeLocale">
  <el-tab-pane v-for="lang in shopLanguages" :name="lang.code">
    <slot :locale="lang.code" :translation="getTranslation(lang.code)" />
  </el-tab-pane>
</el-tabs>
```

**测试**：手动验收 + 后续 E2E（不在 P0 强制）

**依赖**：M03-PR10

---

### M03-PR13 · 后台商品规格 + 变体管理 UI（1d）

**目的**：选规格组（颜色/尺码）→ 自动生成变体矩阵 → 批量编辑价格/库存。

**文件**：
- `frontend/src/views/mall/product/components/SpecificationSelector.vue`
- `frontend/src/views/mall/product/components/VariantMatrix.vue`
- `frontend/src/views/mall/product/components/VariantBatchEdit.vue`

**逻辑**：用户勾选规格 → 前端笛卡尔积生成变体行 → 后端保存时一次性 upsert variants + 关联表。

**依赖**：M03-PR11、M03-PR12

---

### M03-PR14 · 商品翻译切换 + 简单商品快速创建（0.5d）

**目的**：易用性优化——"简单商品"模式（一步创建 SPU + 1 个默认 SKU）。

**文件**：
- `frontend/src/views/mall/product/quick-create.vue`
- `backend/app/Services/Api/Mall/ProductService.php`：新增 `quickCreate()` 方法

**测试**：
- `tests/Feature/Mall/ProductQuickCreateTest.php`：一次请求建 SPU+SKU，库存/价格直接生效

**依赖**：M03-PR13

---

## M04 — 类目 + 品牌（3 工日 · 3 PR）

### M04-PR15 · categories + 翻译 + 树结构（1.5d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_categories_table.php`
- `backend/database/migrations/2026_05_xx_create_category_translations_table.php`
- `backend/app/Models/Mall/Category.php`（用 `kalnoy/nestedset` 或自实现 nested set）
- `backend/app/Http/Controllers/Api/Mall/CategoryController.php`
- `frontend/src/views/mall/category/index.vue`（含树形展示 + 拖拽排序）

**注意**：复用现有 `dept_id` 部门树的实现思路（项目里已有树形结构样板）。

**测试**：
- `tests/Feature/Mall/CategoryTreeTest.php`：CRUD + 拖拽 + 翻译 + 同租户隔离

**依赖**：M03 完成

---

### M04-PR16 · brands + 翻译表（0.5d）

**文件**：常规模式（migration + model + translation + controller + UI）

**字段**：`id, tenant_id, logo, website, sort, status` + `brand_translations(name, description)`

**测试**：`tests/Feature/Mall/BrandCrudTest.php`

**依赖**：M03

---

### M04-PR17 · 后台类目/品牌 UI 完善 + 商品筛选联动（1d）

**目的**：商品列表的类目/品牌筛选下拉（含树形选择器）。

**文件**：
- `frontend/src/views/mall/product/components/CategoryTreeSelect.vue`
- `frontend/src/views/mall/product/components/BrandSelect.vue`

**依赖**：M04-PR15、M04-PR16

---

## M05 — 购物车 + 下单（5 工日 · 5 PR）

### M05-PR18 · carts + cart_items + CartService（1d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_carts_table.php`
- `backend/database/migrations/2026_05_xx_create_cart_items_table.php`
- `backend/app/Models/Mall/Cart.php`
- `backend/app/Models/Mall/CartItem.php`
- `backend/app/Services/Api/Shop/CartService.php`
- `backend/app/Http/Controllers/Api/Shop/CartController.php`

**支持 4 种身份**：登录用户（customer_id）、游客（session_id）、跨设备合并、跨语言保留。

**测试**：
- `tests/Feature/Shop/CartCrudTest.php`：增删改查
- `tests/Feature/Shop/CartGuestToCustomerMergeTest.php`：游客登录后购物车合并

**依赖**：M03 完成

---

### M05-PR19 · orders + order_items + order_addresses（1.5d）

**文件**：
- 3 张表 migration + 3 model + OrderResource + 多 Request
- `backend/app/Services/Api/Shop/OrderService.php`：`createFromCart()` 核心方法
- `backend/app/Enums/OrderStatus.php`（pending / paid / shipped / delivered / cancelled / refunded）

**关键**：下单时**快照**所有信息（商品名称、规格、价格、汇率、地址），订单不依赖 product 表后续变更。

**测试**：
- `tests/Feature/Shop/OrderCreateTest.php`
- `tests/Feature/Shop/OrderSnapshotTest.php`：商品改名后老订单显示快照名

**依赖**：M05-PR18

---

### M05-PR20 · 库存预占机制 + 超时回收 Job（1d）

**目的**：决策 G——下单预占 + 30min 超时释放。

**文件**：
- `backend/app/Services/Api/Shop/InventoryService.php`：`reserve()` / `release()` / `confirmDeduct()`
- `backend/app/Jobs/Mall/ReleaseExpiredOrderReservationsJob.php`
- `backend/app/Console/Commands/Mall/ReleaseExpiredReservationsCommand.php`
- `backend/routes/console.php`：每分钟跑一次

**逻辑**：
- 下单 → `reserved += qty`，订单状态 pending
- 支付成功 → `stock -= qty, reserved -= qty`，订单状态 paid
- 30 分钟未支付 → `reserved -= qty`，订单状态 cancelled

**测试**：
- `tests/Feature/Shop/InventoryReservationTest.php`：并发下单不超卖（用 `withoutMiddleware` + Laravel parallel testing）
- `tests/Feature/Shop/ReleaseExpiredReservationsJobTest.php`

**依赖**：M05-PR19

---

### M05-PR21 · 价格快照 + 汇率换算 + markup（0.5d）

**目的**：决策 H——价格三段式（base_price → 店铺覆盖 → 汇率换算）。

**文件**：
- `backend/app/Services/Api/Mall/PricingService.php`：`resolvePrice($variant, $shop)` 主方法
- `backend/database/migrations/2026_05_xx_create_shop_price_overrides_table.php`
- `backend/app/Models/Mall/ShopPriceOverride.php`

**resolvePrice 逻辑**：
```php
return ShopPriceOverride::where(...)->first()?->price
    ?? convertCurrency($variant->price, $variant->base_currency, $shop->currency) 
       * $shop->price_markup;
```

**测试**：
- `tests/Unit/Mall/PricingServiceTest.php`：覆盖 / 换算 / markup 三场景

**依赖**：M02-PR7（汇率）、M05-PR19

---

### M05-PR22 · 前台下单 API（结账流程）（1d）

**目的**：前台 Nuxt 调用的下单 API（不含支付，支付在 M06）。

**文件**：
- `backend/app/Http/Controllers/Api/Shop/CheckoutController.php`
- 路由 `/api/shop/checkout/preview`（计算总价、运费、税费）
- 路由 `/api/shop/checkout/place-order`（生成订单 + 预占库存）

**测试**：
- `tests/Feature/Shop/CheckoutFlowTest.php`：预览价格正确 → 下单成功 → 库存预占

**依赖**：M05-PR18-21

---

## M06 — 支付（5 工日 · 5 PR）

### M06-PR23 · 支付驱动抽象 + 配置表（1d）

**目的**：决策 E——P0 预留 Driver 抽象，避免硬编码各支付通道。

**文件**：
- `backend/database/migrations/2026_05_xx_create_payment_methods_table.php`
- `backend/app/Models/Mall/PaymentMethod.php`
- `backend/app/Contracts/Payment/PaymentDriverInterface.php`
- `backend/app/Services/Api/Payment/PaymentManager.php`（按 driver code 解析驱动实例）
- `backend/app/Services/Api/Payment/Drivers/AbstractPaymentDriver.php`

**Interface**：
```php
interface PaymentDriverInterface {
    public function charge(Order $order, array $params): PaymentResult;
    public function refund(OrderPayment $payment, float $amount): RefundResult;
    public function handleWebhook(Request $request): WebhookResult;
}
```

**测试**：
- `tests/Unit/Payment/PaymentManagerTest.php`：driver 解析 + 配置加载

**依赖**：M05 完成

---

### M06-PR24 · StripeDriver + Webhook（1.5d）

**文件**：
- `backend/app/Services/Api/Payment/Drivers/StripeDriver.php`
- `backend/app/Http/Controllers/Api/Shop/PaymentWebhookController.php`（统一 webhook 入口）
- `backend/config/services.php`：stripe key 配置
- `composer require stripe/stripe-php`

**关键**：
- 创建 Stripe Checkout Session 返回前端 URL 或 PaymentIntent
- Webhook 验签 + 幂等处理（同一 event_id 不重复处理）
- 成功后调 `InventoryService::confirmDeduct()` + 触发 `OrderPaidEvent`

**测试**：
- `tests/Feature/Payment/StripeChargeTest.php`：mock Stripe API
- `tests/Feature/Payment/StripeWebhookTest.php`：模拟 webhook 签名 + 幂等

**依赖**：M06-PR23

---

### M06-PR25 · WechatDriver（H5 + 小程序）（1.5d）

**文件**：
- `backend/app/Services/Api/Payment/Drivers/WechatDriver.php`
- `composer require yansongda/pay`（推荐）或 `w7corp/easywechat`

**关键**：
- 统一下单（H5 / JSAPI / Native）按场景分支
- 异步通知验签 + XML 解析（旧版微信支付）
- V3 API 优先

**测试**：
- `tests/Feature/Payment/WechatChargeTest.php`
- `tests/Feature/Payment/WechatNotifyTest.php`

**依赖**：M06-PR23

---

### M06-PR26 · order_payments + 统一支付状态机（0.5d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_order_payments_table.php`
- `backend/app/Models/Mall/OrderPayment.php`
- `backend/app/Listeners/Mall/HandleOrderPaidListener.php`（监听 OrderPaidEvent，更新订单状态 + 库存确认 + 发通知）
- `backend/app/Events/Mall/OrderPaidEvent.php`

**字段**：
```php
id, order_id, payment_method, transaction_id UNIQUE,
amount, currency, status(pending|success|failed|refunded),
paid_at, raw_response(json), created_at
```

**测试**：
- `tests/Feature/Payment/OrderPaidEventTest.php`：支付成功后订单状态变 paid + 库存扣减

**依赖**：M06-PR24、M06-PR25

---

### M06-PR27 · 退款链路（0.5d）

**文件**：
- `backend/app/Services/Api/Payment/RefundService.php`
- 后台订单详情页"退款"按钮（M08 阶段做 UI）

**测试**：
- `tests/Feature/Payment/StripeRefundTest.php`
- `tests/Feature/Payment/WechatRefundTest.php`

**依赖**：M06-PR26

---

## M07 — 物流 + 运费（3 工日 · 3 PR）

### M07-PR28 · shipping_methods + rates + zones（1d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_shipping_methods_table.php`
- `backend/database/migrations/2026_05_xx_create_shipping_rates_table.php`
- 复用 M02-PR6 的 zones
- `backend/app/Models/Mall/ShippingMethod.php`
- `backend/app/Models/Mall/ShippingRate.php`
- `backend/app/Http/Controllers/Api/Mall/ShippingMethodController.php`

**rates 字段**：`zone_id, weight_min, weight_max, price, free_threshold`

**测试**：
- `tests/Feature/Mall/ShippingMethodCrudTest.php`

**依赖**：M02-PR6

---

### M07-PR29 · 运费计算 Service（1d）

**文件**：
- `backend/app/Services/Api/Shop/ShippingService.php`：`calculate(Cart $cart, Address $addr)` 主方法

**逻辑**：
- 根据收货国家定位 zone
- 累加购物车总重量
- 匹配 rates 区间
- 满足 free_threshold 免运费

**测试**：
- `tests/Unit/Shop/ShippingCalculateTest.php`：多场景（重量阈值/免运费/zone 不覆盖）

**依赖**：M07-PR28、M05-PR18

---

### M07-PR30 · order_shipments + tracking（1d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_order_shipments_table.php`
- `backend/app/Models/Mall/OrderShipment.php`
- `backend/app/Http/Controllers/Api/Mall/OrderShipmentController.php`：发货 / 修改物流单号

**字段**：`id, order_id, carrier, tracking_no, status, shipped_at, delivered_at, fee`

**测试**：
- `tests/Feature/Mall/OrderShipmentTest.php`：发货 → 状态流转 → 客户能查询

**依赖**：M05-PR19

---

## M08 — 订单管理（4 工日 · 3 PR）

### M08-PR31 · 订单状态机 + history（1d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_order_histories_table.php`
- `backend/app/Models/Mall/OrderHistory.php`
- `backend/app/Services/Api/Mall/OrderStateMachine.php`：状态转换合法性校验
- `backend/app/Observers/OrderObserver.php`：自动写 history

**状态转换图**：
```
pending → paid → shipped → delivered → completed
        ↓        ↓        ↓
     cancelled  refunded  refunded
```

**测试**：
- `tests/Unit/Mall/OrderStateMachineTest.php`：合法/非法转换
- `tests/Feature/Mall/OrderHistoryAutoLogTest.php`

**依赖**：M05-PR19

---

### M08-PR32 · 后台订单列表 + 详情 UI（1.5d）

**文件**：
- `frontend/src/views/mall/order/index.vue`（列表 + 状态筛选 + 搜索）
- `frontend/src/views/mall/order/detail.vue`（详情 + 操作面板）
- `frontend/src/views/mall/order/components/StatusTimeline.vue`
- `frontend/src/views/mall/order/components/OrderItemsTable.vue`
- `frontend/src/api/mall/order.ts`

**依赖**：M08-PR31

---

### M08-PR33 · 发货 / 退款 / 取消操作（1.5d）

**文件**：
- `backend/app/Http/Controllers/Api/Mall/OrderController.php`：`ship`, `refund`, `cancel` 方法
- `frontend/src/views/mall/order/components/ShipDialog.vue`
- `frontend/src/views/mall/order/components/RefundDialog.vue`

**测试**：
- `tests/Feature/Mall/OrderShipActionTest.php`
- `tests/Feature/Mall/OrderCancelActionTest.php`
- `tests/Feature/Mall/OrderRefundActionTest.php`：refund 同时调 RefundService 真实退款

**依赖**：M06-PR27、M07-PR30、M08-PR32

---

## M09 — 客户中心（3 工日 · 3 PR）

### M09-PR34 · customers + addresses + groups（1d）

**文件**：
- `backend/database/migrations/2026_05_xx_create_customers_table.php`
- `backend/database/migrations/2026_05_xx_create_customer_addresses_table.php`
- `backend/database/migrations/2026_05_xx_create_customer_groups_table.php`
- `backend/database/migrations/2026_05_xx_create_customer_group_translations_table.php`
- `backend/app/Models/Mall/Customer.php`
- `backend/app/Models/Mall/CustomerAddress.php`
- `backend/app/Models/Mall/CustomerGroup.php`

**注意**：customer 用 `auth:passport-customer` guard（不同于后台 `auth:api`），独立 token 类型。

**测试**：
- `tests/Feature/Mall/CustomerCrudTest.php`
- `tests/Feature/Shop/CustomerAuthTest.php`：登录拿 token + 访问受保护接口

**依赖**：M01 完成

---

### M09-PR35 · 注册登录 API（手机/邮箱）+ 验证码（1d）

**文件**：
- `backend/app/Http/Controllers/Api/Shop/AuthController.php`
- `backend/app/Services/Api/Shop/AuthService.php`
- `backend/app/Services/Api/Shop/VerificationCodeService.php`
- `backend/config/auth.php`：加 `passport-customer` guard 和 customer provider

**支持**：
- 邮箱注册 + 邮箱验证码
- 手机号注册 + 短信验证码（短信驱动 P0 留 stub，P1 接阿里云/腾讯云）
- 密码登录 + 验证码登录

**测试**：
- `tests/Feature/Shop/AuthEmailRegisterTest.php`
- `tests/Feature/Shop/AuthPhoneLoginTest.php`
- `tests/Feature/Shop/AuthRateLimitTest.php`：验证码 + 登录都加节流

**依赖**：M09-PR34

---

### M09-PR36 · 地址簿 + 我的订单 API（1d）

**文件**：
- `backend/app/Http/Controllers/Api/Shop/CustomerAddressController.php`
- `backend/app/Http/Controllers/Api/Shop/CustomerOrderController.php`
- `backend/app/Http/Controllers/Api/Shop/CustomerProfileController.php`

**API**：
- `GET /api/shop/me` 当前用户信息
- `GET/POST/PUT/DELETE /api/shop/me/addresses`
- `GET /api/shop/me/orders` 我的订单（仅本人）

**测试**：
- `tests/Feature/Shop/CustomerAddressCrudTest.php`
- `tests/Feature/Shop/CustomerMyOrdersTest.php`：A 客户看不到 B 客户订单

**依赖**：M09-PR35、M05-PR19

---

## M10 — 后台 UI 整合（5 工日 · 5 PR）

> 把 M03-M09 的后台 Vue 视图整合进现有 dsweixin 后台菜单 + 权限。

### M10-PR37 · mall 模块菜单 + 路由 + 权限（1d）

**文件**：
- `backend/database/seeders/MallMenuSeeder.php`：插入 mall 菜单树（商品/订单/客户/营销/设置）
- `backend/database/seeders/MallPermissionSeeder.php`：mall 权限点
- `frontend/src/router/modules/mall.ts`：动态路由
- `frontend/src/layouts/components/Sidebar/index.vue`（如需调整菜单组）

**菜单树**：
```
商城
├── 商品
│   ├── 商品列表
│   ├── 类目管理
│   ├── 品牌管理
│   ├── 规格管理
│   └── 属性管理
├── 订单
├── 客户
│   ├── 客户列表
│   └── 客户分组
├── 设置
│   ├── 店铺设置
│   ├── 支付方式
│   ├── 物流方式
│   └── 多语言/币种
```

**测试**：
- `tests/Feature/Mall/MallMenuPermissionTest.php`：权限不足看不到菜单

**依赖**：M03-M09 完成

---

### M10-PR38 · 商品管理界面整合 + 完善（1d）

**目的**：统一商品列表/创建/编辑视觉，对齐现有 dsweixin 后台风格。

**依赖**：M03 全部 PR

---

### M10-PR39 · 类目/品牌/规格/属性界面整合（1d）

**依赖**：M04 全部 PR

---

### M10-PR40 · 订单管理界面整合（1d）

**依赖**：M08 全部 PR

---

### M10-PR41 · 客户管理界面整合（1d）

**依赖**：M09 全部 PR

---

## M11 — Nuxt 3 SSR 前台（7 工日 · 6 PR）

### M11-PR42 · Nuxt 3 工程脚手架（1d）

**文件**：
- `frontend-shop/`（新工程根）
  - `nuxt.config.ts`
  - `package.json`（nuxt@3 + vue-i18n + tailwindcss + unplugin-icons）
  - `tsconfig.json`
  - `app.vue`
  - `tailwind.config.ts`
  - `i18n.config.ts`
- `frontend-shop/middleware/tenant.global.ts`：根据 host 拉 shop 配置（API: `/api/shop/config`）
- `frontend-shop/composables/useShop.ts`
- `frontend-shop/composables/useApi.ts`（含 baseURL + token 管理）
- `frontend-shop/.env.example`

**关键 nuxt.config.ts**：
```ts
export default defineNuxtConfig({
  ssr: true,
  runtimeConfig: {
    public: { apiBase: process.env.API_BASE_URL }
  },
  modules: ['@nuxtjs/i18n', '@nuxtjs/tailwindcss', '@pinia/nuxt'],
  i18n: { strategy: 'prefix_except_default', locales: [...] }
})
```

**.github/workflows/ci.yml**：加 `frontend-shop` 的 lint + typecheck + build job（参考 frontend job）

**依赖**：M10 完成（API 已稳定）

---

### M11-PR43 · 首页 + 类目导航 + 商品列表页（1.5d）

**文件**：
- `frontend-shop/pages/index.vue`（首页 SSR）
- `frontend-shop/pages/category/[slug].vue`（类目商品列表 SSR + SEO meta）
- `frontend-shop/components/Header.vue`、`Footer.vue`
- `frontend-shop/components/CategoryNav.vue`
- `frontend-shop/components/ProductCard.vue`

**SEO**：每个页面 `useHead({ title, meta: [...] })` 注入 SEO 数据。

**依赖**：M11-PR42

---

### M11-PR44 · 商品详情页（SEO 关键）（1.5d）

**文件**：
- `frontend-shop/pages/product/[slug].vue`
- `frontend-shop/components/product/ImageGallery.vue`
- `frontend-shop/components/product/SpecSelector.vue`
- `frontend-shop/components/product/AddToCartButton.vue`

**SSR + SEO**：
- 商品名 + 描述写入 `<title>` `<meta description>`
- Open Graph 标签（og:title, og:image）
- JSON-LD Product schema（Google Rich Snippets）
- canonical URL（同一商品多语言用 `<link rel="alternate" hreflang="...">`）

**依赖**：M11-PR43

---

### M11-PR45 · 购物车 + 结账流程（1.5d）

**文件**：
- `frontend-shop/pages/cart.vue`
- `frontend-shop/pages/checkout/index.vue`（地址选择）
- `frontend-shop/pages/checkout/payment.vue`（支付选择）
- `frontend-shop/pages/checkout/success.vue`
- `frontend-shop/composables/useCart.ts`
- `frontend-shop/stores/cart.ts`（pinia）
- `frontend-shop/components/checkout/AddressForm.vue`
- `frontend-shop/components/checkout/PaymentSelector.vue`

**关键**：
- 购物车支持游客（localStorage）+ 登录用户（API）
- 结账时整合运费 / 税费 / 优惠 / 总价预览
- 支付选择后跳支付通道（Stripe Checkout / 微信 H5）

**依赖**：M11-PR44、M05、M06、M07

---

### M11-PR46 · 我的中心（订单/地址/退出）（1d）

**文件**：
- `frontend-shop/pages/account/index.vue`
- `frontend-shop/pages/account/orders/index.vue`
- `frontend-shop/pages/account/orders/[id].vue`
- `frontend-shop/pages/account/addresses.vue`
- `frontend-shop/pages/login.vue`
- `frontend-shop/pages/register.vue`
- `frontend-shop/middleware/auth.ts`

**依赖**：M11-PR45、M09

---

### M11-PR47 · 多语言 + 多币种切换 + 主题色（0.5d）

**文件**：
- `frontend-shop/components/LocaleSwitcher.vue`
- `frontend-shop/components/CurrencySwitcher.vue`
- `frontend-shop/composables/useTheme.ts`（接收 shop.theme 配置动态注入 CSS 变量）

**依赖**：M11-PR46

---

## 12. 关键路径甘特图

```
W1 ─ M01 SaaS 改造 ──┐
W1 ─ M02 全球化基础 ─┤
                    ├─ M03 商品 4 层 ──────────┐
W2-W3                                          │
                                               ├─ M05 购物车下单 ──┐
W4 ─ M04 类目品牌 ─┘                          │                  │
                                               ├─ M06 支付 ───────┤
                    ┌──────────────────────────┘                  │
W4-W5               │                          ┌─ M07 物流 ───────┤
                    │  M09 客户中心 ─┐         │                  ├─ M11 Nuxt 前台
                    │                ├─ M08 订单管理 ─────────────┤
                    │                │         │                  │
W5-W6               └────────────────┴────────M10 后台 UI 整合 ───┘
```

**关键路径**：M01 → M02 → M03 → M05 → M06 → M11（前 6 模块顺序敏感，后 5 模块可并行）

**并行机会**：
- M02 和 M01-PR4 可并行
- M07 / M08 / M09 三个模块在 M05/M06 完成后可三人并行
- M11 前台和 M10 后台 UI 整合可并行（API 是契约）

---

## 13. 验收标准（P0 完成判定）

### 功能验收

- [ ] 平台超管能创建租户 + 套餐
- [ ] 租户管理员能创建 2 个店铺（一中一英），各绑定不同 subdomain
- [ ] 创建一个商品（含中英双语 + 颜色×尺码 4 个变体）
- [ ] 消费者从 `acme-cn.dsweixin.com` 能浏览商品 → 加购 → 下单（CNY 计价）
- [ ] 微信支付能跑通真实交易（沙箱环境）
- [ ] 后台能看到订单 + 发货 + 客户能查物流
- [ ] 消费者从 `acme-en.dsweixin.com` 看到的是英文 + USD 计价（同一商品自动换算）

### 技术验收

- [ ] `composer test` 通过（含新增 mall 测试，预计 100+ Feature + 30+ Unit）
- [ ] `composer stan` 通过（baseline 不增长）
- [ ] `composer pint --test` 通过
- [ ] `npm run lint:check` + `typecheck` 后台和前台都通过
- [ ] CI 全绿
- [ ] Lighthouse 商品详情页 Performance > 80、SEO = 100
- [ ] 老 130 个 test 全部通过（无回归）

### 文档验收

- [ ] 每个 Service 类有 phpdoc
- [ ] API 接口有简易说明（P1 引入 OpenAPI）
- [ ] 数据库 ER 图（用 dbdiagram.io 生成 PNG）放在 `docs/database-mall.md`

---

## 14. 风险预案

| 风险 | 触发场景 | 应急方案 |
|---|---|---|
| Stripe / 微信支付沙箱申请慢 | 注册/审核耗时 | 先用本地 mock driver，让前后端跑通，沙箱到位后切换 |
| 翻译表 N+1 性能问题 | 商品列表页慢 | 强制 `with('translations')` + 加 Redis 缓存（按 locale 缓存 1h）|
| Nuxt 3 SSR 国内访问慢 | npm 包装不上 | 用淘宝 npm 镜像 + `NPM_CONFIG_REGISTRY` |
| 多租户路由冲突 | host 解析错租户 | 子域名严格白名单 + 单元测试覆盖所有形态 |
| 商品 4 层 UI 复杂 | 商户上手难 | "简单商品快速创建"模式（M03-PR14）兜底 |
| 库存并发超卖 | 高并发下单 | DB 行锁 + Redis 分布式锁双保险，性能压测必做 |
| 工期紧张 | 单人 9 周不现实 | 关键路径 M01→M03→M05→M06 不可压缩；其余可砍可推（如运费先一刀切） |

---

## 15. 砍掉也能上线的清单（极限压缩 P0）

如果工期紧迫，以下可以延后到 P1：

| 模块 | 延后影响 |
|---|---|
| M02-PR7 汇率自动同步 | 改手动配置汇率即可 |
| M03-PR14 简单商品快速创建 | 用户多点几步 |
| M03-PR9 attributes（属性，非选购维度）| 推迟，先用商品描述凑 |
| M04-PR16 brands | 商品先不分品牌 |
| M06-PR27 退款 | 先支持后台手动备注，下个版本接 API |
| M07 物流多级运费 | 先用统一固定运费 |
| M11-PR47 多语言/币种切换 | 各店铺独立子域名固定一种语言 |

**最小可用 MVP 砍剩 ≈ 32 工日**（6 周单人）。

---

## 16. 下一步建议

1. ✅ 你审阅本任务清单，对工作量、PR 拆分、关键路径无异议
2. 决定团队配置：
   - **单人**：按本清单顺序做，9 周完成 P0
   - **双人**：一人后端（M01-09 核心）+ 一人前端（M10-M11），5-6 周完成
   - **三人**：再加一人专门做 M11 Nuxt 前台，4-5 周完成
3. 确定 W1 启动日期 → 拉 `feat/mall-mvp` 分支 → 开 M01-PR1
4. （可选）每周固定时间复盘进度，更新本文档的"已完成 PR"列

---

## 附录：PR 索引表（一览查阅）

| # | PR | 模块 | 工日 | 依赖 |
|---|---|---|---|---|
| 1 | M01-PR1 tenants 扩字段 | M01 | 0.5 | - |
| 2 | M01-PR2 merchants→shops | M01 | 1.0 | PR1 |
| 3 | M01-PR3 TenantMiddleware host 解析 | M01 | 0.5 | PR2 |
| 4 | M01-PR4 plans 套餐表 | M01 | 1.0 | PR1 |
| 5 | M02-PR5 lang/currency/country | M02 | 1.0 | PR4 |
| 6 | M02-PR6 zones | M02 | 0.5 | PR5 |
| 7 | M02-PR7 exchange_rates | M02 | 0.5 | PR5 |
| 8 | M03-PR8 specifications | M03 | 1.0 | PR7 |
| 9 | M03-PR9 attributes | M03 | 1.0 | PR8 |
| 10 | M03-PR10 products 主表 | M03 | 1.5 | PR8,9 |
| 11 | M03-PR11 product_variants | M03 | 1.0 | PR10 |
| 12 | M03-PR12 后台商品 UI 基础 | M03 | 1.0 | PR10 |
| 13 | M03-PR13 后台变体 UI | M03 | 1.0 | PR11,12 |
| 14 | M03-PR14 简单商品快速创建 | M03 | 0.5 | PR13 |
| 15 | M04-PR15 categories 树 | M04 | 1.5 | PR14 |
| 16 | M04-PR16 brands | M04 | 0.5 | PR14 |
| 17 | M04-PR17 商品筛选联动 UI | M04 | 1.0 | PR15,16 |
| 18 | M05-PR18 carts | M05 | 1.0 | PR17 |
| 19 | M05-PR19 orders | M05 | 1.5 | PR18 |
| 20 | M05-PR20 库存预占 | M05 | 1.0 | PR19 |
| 21 | M05-PR21 价格快照 | M05 | 0.5 | PR19 |
| 22 | M05-PR22 前台下单 API | M05 | 1.0 | PR18-21 |
| 23 | M06-PR23 支付驱动抽象 | M06 | 1.0 | PR22 |
| 24 | M06-PR24 StripeDriver | M06 | 1.5 | PR23 |
| 25 | M06-PR25 WechatDriver | M06 | 1.5 | PR23 |
| 26 | M06-PR26 order_payments | M06 | 0.5 | PR24,25 |
| 27 | M06-PR27 退款 | M06 | 0.5 | PR26 |
| 28 | M07-PR28 shipping_methods | M07 | 1.0 | PR6 |
| 29 | M07-PR29 运费计算 | M07 | 1.0 | PR28 |
| 30 | M07-PR30 order_shipments | M07 | 1.0 | PR19 |
| 31 | M08-PR31 订单状态机 | M08 | 1.0 | PR19 |
| 32 | M08-PR32 后台订单 UI | M08 | 1.5 | PR31 |
| 33 | M08-PR33 发货退款取消 | M08 | 1.5 | PR27,30,32 |
| 34 | M09-PR34 customers | M09 | 1.0 | PR2 |
| 35 | M09-PR35 customer 注册登录 | M09 | 1.0 | PR34 |
| 36 | M09-PR36 customer 我的中心 API | M09 | 1.0 | PR35,19 |
| 37 | M10-PR37 mall 菜单权限 | M10 | 1.0 | M03-09 |
| 38 | M10-PR38 商品 UI 整合 | M10 | 1.0 | M03 |
| 39 | M10-PR39 类目品牌 UI 整合 | M10 | 1.0 | M04 |
| 40 | M10-PR40 订单 UI 整合 | M10 | 1.0 | M08 |
| 41 | M10-PR41 客户 UI 整合 | M10 | 1.0 | M09 |
| 42 | M11-PR42 Nuxt 工程脚手架 | M11 | 1.0 | M10 |
| 43 | M11-PR43 首页+类目页 | M11 | 1.5 | PR42 |
| 44 | M11-PR44 商品详情页 SEO | M11 | 1.5 | PR43 |
| 45 | M11-PR45 购物车结账 | M11 | 1.5 | PR44,M5,M6,M7 |
| 46 | M11-PR46 我的中心 | M11 | 1.0 | PR45,M9 |
| 47 | M11-PR47 多语言/币种切换 | M11 | 0.5 | PR46 |
| **总计** | | | **47.0** | |
