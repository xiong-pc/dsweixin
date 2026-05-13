# 跨境电商 SaaS 系统规划（v2.0）

> 本文档替代 `2026-04-24-mall-system-requirements.md` 中的"商城系统"部分。
> 旧需求文档仍保留作为单租户单语言场景的参考。

---

## 1. 文档定位

把 dsweixin 从"多租户后台脚手架"升级为 **多租户跨境电商 SaaS 平台**，融合两个参考项目的精华：

- **Teanary 1.4** — AI 原生跨境电商后端（Laravel 12 + Livewire）
- **BeikeShop 1.5** — 开箱即用跨境建站（Laravel 10 + 插件生态）
- **dsweixin 现状** — Laravel 13 + Vue 3 SPA + 多租户底座 + RBAC

---

## 2. 战略决策（已确认）

| ID | 决策 | 影响 |
|---|---|---|
| **A** | 多租户 SaaS（Shopify 式）| 一套代码服务所有租户 |
| **B** | 租户 → 店铺（语言站点）| 一个品牌可开多个本地化店铺 |
| **C** | Nuxt 3 SSR 前台 | SEO 友好，跨境必备 |
| **D** | 独立翻译表 | 业界标准，每个核心实体一张 `*_translations` 副表 |

### 三层组织结构

```
平台（dsweixin SaaS）
└── 租户 tenant（品牌方，如 "Acme Corp"）
    ├── 店铺 shop（语言站点）
    │   ├── acme-cn  → zh-CN, CNY, 微信支付
    │   ├── acme-us  → en-US, USD, Stripe + PayPal
    │   └── acme-jp  → ja-JP, JPY, PayPay
    ├── 共享后台（商品库、运营人员、订单总览）
    └── 共享主数据（品牌、属性、规格、客户）
```

---

## 3. 三项目能力对比与借鉴策略

| 维度 | Teanary | BeikeShop | dsweixin 现状 | **新 dsweixin** |
|---|---|---|---|---|
| 多租户 | ❌ 单租户 | ❌ 单租户 | ✅ 租户+商户 | ✅ 租户+店铺 ⬆️ |
| 翻译表模型 | ✅ 🏆 | ✅ | ❌ | ✅ **采用 Teanary 风格**（`*_translations`）|
| 多币种 | ✅ 🏆 | ✅ | ❌ | ✅ 采用 |
| 多仓库 | ✅ 🏆 | ❌ | ❌ | ✅ P1 采用 |
| 商品 4 层（SPU/SKU/规格/属性）| ✅ 🏆 | ✅ 3 层 | ❌ | ✅ **采用 Teanary 4 层** |
| 促销引擎 | ✅ 🏆 | 基础 | ❌ | ✅ 采用 Teanary |
| 税费三级（Class/Rate/Rule）| ❌ | ✅ 🏆 | ❌ | ✅ **采用 BeikeShop** |
| RMA 退换货 | 基础 | ✅ 🏆 | ❌ | ✅ **采用 BeikeShop** |
| 插件 + 主题系统 | ❌ | ✅ 🏆 | ❌ | ✅ P1 采用 BeikeShop Hook |
| AI 原生 MCP | ✅ 🏆 | ❌ | ❌ | ✅ P2 采用 Teanary |
| 多节点同步 | ✅ 🏆 | ❌ | ❌ | ⏸️ 暂不做（单节点先跑通）|
| Sentry/Horizon | ✅ | ✅ | ❌ | ✅ P1 |
| Meilisearch 全文搜 | ✅ 🏆 | ❌ | ❌ | ✅ P1 |
| CI/CD/质量门 | ✅ | 部分 | ✅ 🏆 | ✅ 已就位 |
| RBAC 数据权限 | 基础 | 基础 | ✅ 🏆 | ✅ 扩展到店铺级 |
| 前端 SSR | ❌ Livewire | ❌ Blade | ❌ SPA | ✅ **Nuxt 3 SSR** ⬆️ |

---

## 4. 五层架构

```
┌─────────────────────────────────────────────────────┐
│ L5: AI 运营层（Teanary MCP 原则）              P2  │
│    销售/流量分析 · 翻译草稿 · 定价建议 · 异常预警  │
│    AI 不直接访问 DB，通过受控 Service 接口        │
├─────────────────────────────────────────────────────┤
│ L4: 插件 + 主题层（BeikeShop Hook + Nuxt theme）P1│
│    支付/物流/营销/导入导出插件 · Nuxt 主题热换    │
├─────────────────────────────────────────────────────┤
│ L3: 跨境电商业务层                               P0│
│    商品(SPU/SKU/Spec/Attr) · 订单 · RMA · 促销   │
│    多仓 · 税费三级 · 币种 · Zone · 翻译表        │
├─────────────────────────────────────────────────────┤
│ L2: 多租户 SaaS 层（dsweixin 扩展）        ⬆️ 改造│
│    租户 · 店铺 · 子域路由 · 数据隔离 · 套餐计费   │
├─────────────────────────────────────────────────────┤
│ L1: 平台基础层（dsweixin 现状）               ✅ 完│
│    RBAC · Passport · 字典 · 菜单 · 区域 · 日志   │
└─────────────────────────────────────────────────────┘
```

---

## 5. 数据模型骨架

> 完整 ER 见后续 P0 实现 PR；此处先给核心实体的字段提纲。

### 5.1 SaaS 层（L2 扩展）

```sql
-- 现有 tenants 表扩字段（不破坏现有租户）
ALTER TABLE tenants ADD COLUMN
    plan_id            bigint NULL COMMENT '套餐 ID',
    primary_domain     varchar(255) NULL COMMENT '主域名 acme.com',
    default_locale     varchar(10) DEFAULT 'zh-CN',
    default_currency   varchar(3)  DEFAULT 'CNY',
    industry           varchar(50) NULL COMMENT '行业',
    expires_at         timestamp NULL COMMENT '套餐到期';

-- 现有 merchants 表演化为 shops
RENAME TABLE merchants TO shops;
ALTER TABLE shops ADD COLUMN
    locale     varchar(10) COMMENT '主语言 zh-CN/en-US/ja-JP',
    currency   varchar(3)  COMMENT '主币种',
    subdomain  varchar(64) UNIQUE COMMENT '子域名 acme-cn',
    timezone   varchar(64) DEFAULT 'Asia/Shanghai',
    theme_id   bigint NULL,
    sort       int DEFAULT 0,
    status     tinyint DEFAULT 1;

-- 平台级套餐表
CREATE TABLE plans (
    id, name, code, max_shops, max_products,
    max_orders_per_month, monthly_price, yearly_price,
    features_json, status, created_at, updated_at
);
```

### 5.2 全球化基础数据（L3 跨境核心）

```sql
-- 语言（平台级 + 店铺启用）
CREATE TABLE languages (
    id, code(zh-CN), name, native_name, flag, rtl, status
);

-- 币种 + 汇率
CREATE TABLE currencies (
    id, code(USD), symbol, name, decimal_places, status
);
CREATE TABLE exchange_rates (
    id, from_currency, to_currency, rate, effective_at, source
);

-- 国家 + 翻译
CREATE TABLE countries (
    id, code(US), iso3(USA), phone_code, currency_code,
    continent, status
);
CREATE TABLE country_translations (id, country_id, locale, name);

-- 区域分组（Zone）— 运费/税费分组依据
CREATE TABLE zones (id, tenant_id, code, name, sort);
CREATE TABLE zone_countries (zone_id, country_id);
```

### 5.3 商品 4 层模型（采用 Teanary）

```sql
-- SPU 商品主体
CREATE TABLE products (
    id, tenant_id, shop_id NULL,    -- shop_id=NULL 表示全店铺共享
    brand_id, category_id, sku_prefix,
    cover_image, images_json,
    base_price, base_currency,
    status, sort, sold_count, view_count,
    created_at, updated_at, deleted_at
);
CREATE TABLE product_translations (
    id, product_id, locale,
    name, slug, short_description, description,
    seo_title, seo_keywords, seo_description
);

-- 规格组（如"颜色"、"尺码"）
CREATE TABLE specifications (id, tenant_id, code, status);
CREATE TABLE specification_translations (id, specification_id, locale, name);
CREATE TABLE specification_values (id, specification_id, code, color_hex);
CREATE TABLE specification_value_translations (id, value_id, locale, name);

-- SKU 商品变体
CREATE TABLE product_variants (
    id, product_id, sku, barcode,
    price, compare_at_price, cost,
    weight, weight_unit, dimensions_json,
    stock, low_stock_threshold,
    image, status
);
CREATE TABLE product_variant_specification_values (
    variant_id, specification_value_id
);

-- 属性组（如"材质"、"产地"，非选购维度）
CREATE TABLE attributes (id, tenant_id, code, type, status);
CREATE TABLE attribute_translations (id, attribute_id, locale, name);
CREATE TABLE attribute_values (id, attribute_id, code);
CREATE TABLE attribute_value_translations (id, value_id, locale, name);
CREATE TABLE product_attribute_values (product_id, attribute_id, value_id);

-- 类目
CREATE TABLE categories (
    id, tenant_id, parent_id, image, sort, status
);
CREATE TABLE category_translations (
    id, category_id, locale, name, slug, seo_title, seo_description
);

-- 品牌
CREATE TABLE brands (id, tenant_id, logo, website, sort, status);
CREATE TABLE brand_translations (id, brand_id, locale, name, description);
```

### 5.4 订单 + 支付 + 物流

```sql
CREATE TABLE orders (
    id, tenant_id, shop_id, customer_id,
    order_no UNIQUE, status,
    currency, exchange_rate,
    subtotal, shipping_fee, tax_fee, discount_fee, total,
    paid_at, shipped_at, delivered_at,
    cancelled_at, cancel_reason,
    locale, ip_country,           -- 下单时记录
    note, created_at, updated_at
);
CREATE TABLE order_items (
    id, order_id, product_id, variant_id,
    sku, name_snapshot, image_snapshot, spec_text_snapshot,
    unit_price, quantity, line_total
);
CREATE TABLE order_addresses (
    id, order_id, type(billing|shipping),
    country, state, city, address1, address2,
    postcode, name, phone, email
);
CREATE TABLE order_payments (
    id, order_id, payment_method, transaction_id,
    amount, currency, status, paid_at, raw_response_json
);
CREATE TABLE order_shipments (
    id, order_id, carrier, tracking_no, status,
    shipped_at, delivered_at, fee
);
CREATE TABLE order_histories (
    id, order_id, status_from, status_to,
    operator_type, operator_id, note, created_at
);
```

### 5.5 税费三级（采用 BeikeShop）

```sql
CREATE TABLE tax_classes (id, tenant_id, code, name);   -- 服装 / 数码 / 食品
CREATE TABLE tax_rates  (id, tenant_id, zone_id, name, rate, priority);
CREATE TABLE tax_rules  (id, tenant_id, tax_class_id, tax_rate_id, based_on);
```

### 5.6 售后 RMA（采用 BeikeShop）

```sql
CREATE TABLE rmas (
    id, order_id, customer_id, type(refund|return|exchange),
    reason_id, status, refund_amount, evidence_images,
    created_at, processed_at
);
CREATE TABLE rma_histories (id, rma_id, status, note, operator, created_at);
CREATE TABLE rma_reasons   (id, tenant_id, code, sort);
CREATE TABLE rma_reason_translations (id, reason_id, locale, name);
```

### 5.7 促销引擎（采用 Teanary）

```sql
CREATE TABLE promotions (
    id, tenant_id, shop_id, code, type(discount|bxgy|gift|coupon),
    starts_at, ends_at, status, usage_limit, used_count
);
CREATE TABLE promotion_translations (id, promotion_id, locale, name, description);
CREATE TABLE promotion_rules (
    id, promotion_id, condition_type, condition_value, action_type, action_value
);
CREATE TABLE promotion_products (promotion_id, product_id);
CREATE TABLE promotion_variants (promotion_id, variant_id);
CREATE TABLE promotion_customer_groups (promotion_id, customer_group_id);

CREATE TABLE customer_groups (id, tenant_id, code, priority);
CREATE TABLE customer_group_translations (id, group_id, locale, name);
```

### 5.8 客户

```sql
CREATE TABLE customers (
    id, tenant_id, shop_id NULL,     -- shop_id=NULL 表示跨店铺账号
    email, phone, password,
    name, avatar, gender, birthday,
    group_id, status, last_login_at, created_at
);
CREATE TABLE customer_addresses (id, customer_id, is_default, country, ...);
CREATE TABLE customer_wishlists (customer_id, product_id, created_at);
```

### 5.9 多仓库 P1（采用 Teanary）

```sql
CREATE TABLE warehouses (id, tenant_id, code, country_id, address, sort, status);
CREATE TABLE warehouse_translations (id, warehouse_id, locale, name);
CREATE TABLE product_warehouse_stocks (warehouse_id, variant_id, stock, reserved);
```

---

## 6. 模块清单与里程碑

### P0 MVP（4-6 周）— 单租户能完整卖货

| # | 模块 | 子项 | 估时 |
|---|---|---|---|
| M01 | SaaS 层改造 | tenants 扩字段、merchants → shops | 3d |
| M02 | 全球化基础 | languages / currencies / countries / zones + 翻译 | 2d |
| M03 | 商品 4 层 | products + variants + specs + attrs + 全套翻译表 | 7d |
| M04 | 类目 + 品牌 | + 翻译 + 拖拽排序 | 3d |
| M05 | 购物车 + 下单 | 多币种价格快照、库存预占 | 5d |
| M06 | 支付（Stripe + PayPal + 微信）| 抽象支付驱动接口 | 5d |
| M07 | 物流 + 运费模板 | 按 Zone + 重量计费 | 3d |
| M08 | 订单管理 | 后台订单流转 + 状态机 + 日志 | 4d |
| M09 | 客户中心 | 注册登录 + 地址簿 + 订单查询 | 3d |
| M10 | 后台 UI | 现有 Vue 3 后台扩商品/订单/客户菜单 | 5d |
| M11 | 前台 Nuxt 3 骨架 | 首页/类目/商品详情/购物车/结账/我的 | 7d |
| **总计** | | | **约 47 工日** |

### P1 生态（6-8 周）— 跨境深化 + 平台化

| # | 模块 | 借鉴 |
|---|---|---|
| M12 | 多店铺切换 + 子域路由 | 自研 |
| M13 | 税费三级 | BeikeShop |
| M14 | RMA 退换货 | BeikeShop |
| M15 | 多仓库 + 分仓库存 | Teanary |
| M16 | 促销引擎（规则 + 客户组）| Teanary |
| M17 | 客户分组 + 分组价 | Teanary |
| M18 | 插件机制（Hook + Service Provider）| BeikeShop |
| M19 | Nuxt 主题系统 | 自研 |
| M20 | CMS 页面 + SEO sitemap | BeikeShop |
| M21 | 社交登录（Google/FB/微信）| BeikeShop Socialite |
| M22 | 库存导入导出 Excel | BeikeShop |
| M23 | Meilisearch 全文搜 | Teanary |
| M24 | Sentry + Horizon | 两家都用 |

### P2 智能（8-12 周）— AI + 全球化

| # | 模块 | 借鉴 |
|---|---|---|
| M25 | AI 运营助手（MCP 受控接口）| Teanary 1.4 |
| M26 | 商品翻译草稿自动生成 | Teanary |
| M27 | 销售/流量/转化 BI 看板 | 自研 |
| M28 | 库存预警 + 价格异常检测 | Teanary |
| M29 | 多节点同步（可选）| Teanary |
| M30 | App 端（uniapp/React Native）| 自研 |

---

## 7. 工程结构调整

```
dsweixin/
├── backend/                       # Laravel 13 API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── System/            # 系统管理 ✅ 已有
│   │   │   ├── Common/            # 通用 ✅ 已有（area 等）
│   │   │   ├── Mall/              # 🆕 商城后台
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   └── ...
│   │   │   └── Shop/              # 🆕 前台 API（消费者端）
│   │   │       ├── CatalogController.php
│   │   │       ├── CartController.php
│   │   │       ├── CheckoutController.php
│   │   │       └── CustomerController.php
│   │   ├── Services/Api/
│   │   │   ├── Mall/              # 🆕
│   │   │   ├── Shop/              # 🆕
│   │   │   ├── Payment/           # 🆕 支付驱动
│   │   │   │   ├── StripeDriver.php
│   │   │   │   ├── PaypalDriver.php
│   │   │   │   └── WechatDriver.php
│   │   │   └── Shipping/          # 🆕 物流驱动
│   │   └── Models/
│   │       ├── Mall/              # 🆕 Product/Order/Customer 等
│   │       └── ...
│   └── routes/api.php
│
├── frontend/                      # Vue 3 SPA 后台（运营+商户）
│   └── src/views/
│       ├── system/                # ✅ 已有
│       ├── common/                # ✅ 已有
│       └── mall/                  # 🆕 商城运营界面
│           ├── product/
│           ├── order/
│           ├── customer/
│           └── ...
│
├── frontend-shop/                 # 🆕 Nuxt 3 SSR 前台（消费者）
│   ├── pages/
│   │   ├── index.vue              # 首页（SSR）
│   │   ├── category/[slug].vue
│   │   ├── product/[slug].vue     # 商品详情（SEO 关键）
│   │   ├── cart.vue
│   │   ├── checkout/...
│   │   └── account/...
│   ├── components/
│   ├── composables/
│   │   ├── useShop.ts             # 当前店铺/语言/币种
│   │   ├── useCart.ts
│   │   └── useApi.ts
│   ├── middleware/
│   │   └── tenant.global.ts       # 根据 host 拉取店铺配置
│   └── i18n/                      # vue-i18n 文案包
│
└── docs/
    └── superpowers/specs/
        └── 2026-05-12-cross-border-mall-saas-plan.md  ← 本文档
```

---

## 8. 实施路线图（按周）

| 周次 | 重点 | 验收 |
|---|---|---|
| **W1** | M01 SaaS 改造 + M02 全球化基础 | 租户能注册并选语言/币种；shops 表迁移完毕 |
| **W2** | M03 商品 4 层（前半）| products + variants 表建好，CRUD 跑通 |
| **W3** | M03 后半 + M04 类目/品牌 | 完整商品创建（含规格/属性/翻译）流程 |
| **W4** | M05 购物车 + M06 支付 | 至少一个支付通道（推荐 Stripe）跑通真实交易 |
| **W5** | M07 物流 + M08 订单管理 + M09 客户 | 后台能完整流转订单 |
| **W6** | M10 后台 UI + M11 Nuxt 前台骨架 | 用户能从前台浏览→下单→收货全链路 |
| W7-W8 | P0 收尾 + 性能/兼容测试 | 100+ 自动化测试通过、Lighthouse > 90 |
| W9-W14 | P1 模块（按优先级）| - |
| W15+ | P2 AI 与全球化 | - |

---

## 9. 风险与待决策项

### 风险

| 风险 | 影响 | 应对 |
|---|---|---|
| 商品 4 层模型对小卖家过于复杂 | 易用性低 | 后台提供"简单商品"快速创建模式（一步生成 SPU+1 SKU）|
| 翻译表导致核心查询 N+1 | 性能 | 强制 `with('translations')`；中间件按 locale 过滤 |
| Nuxt SSR 服务器成本上升 | 基础设施 | 静态生成可缓存页面（类目页、商品详情）|
| 跨境支付合规复杂 | 上线时间 | P0 先支持 Stripe（覆盖最多国家）+ 微信（中国）|
| 多店铺数据权限分配 | 安全 | RBAC 加 shop_id 维度，复用 spatie/permission teams |

### 决策汇总（A-K 全部确认 · 2026-05-12）

| ID | 决策 | 选定方案 |
|---|---|---|
| A | 租户形态 | **多租户 SaaS（Shopify 式）** |
| B | 商户层 | **租户 → 多店铺（语言站点）** |
| C | 前端形态 | **后台 Vue 3 SPA + 前台 Nuxt 3 SSR** |
| D | 多语言存储 | **独立翻译表 `*_translations`** |
| **E** | 插件机制 | **P0 预留 Driver 抽象 + ServiceProvider 钩子；P1 完整 Hook 体系** |
| **F** | 支付通道 | **P0：Stripe（主推） + 微信支付（H5/小程序）；P0 末期补 PayPal；支付宝国际/分期 P1** |
| **G** | 库存策略 | **下单预占 + 30 分钟超时释放；`product_variants.stock` 总量 + `reserved` 预占；scheduler 每分钟扫超时** |
| **H** | 价格策略 | **主表 `base_price` + `base_currency` + 店铺覆盖价 + 汇率自动换算 + `markup` 倍率（防汇率波动）** |
| **I** | 域名方案 | **P0 子域名 `acme.dsweixin.com`（DNS 配通配 + Host 解析 shop）；P1 自定义 CNAME + Caddy/ACME 自动签 SSL** |
| **J** | 会员中心 | **P0 仅 customers + addresses + groups（最小集）；P1 引入 `2026-04-24-member-center-design.md` 的等级/积分/标签；P2 余额/佣金/多渠道绑定** |
| **K** | 代码生成器 | **扩展现有 `gen:code` 加 `with_i18n` 开关，自动生成主表 + `*_translations` + Resource join + Vue 多语言 tab** |

---

## 10. 与现有代码的兼容

### 不会破坏的部分
- `frontend/` 后台所有模块照常运行（system / common / area 等）
- 现有 130 个 Feature test + 20 Unit test 不动
- CI/CD 流程不变（pint + phpstan + eslint + PCOV）
- Passport OAuth 复用（消费者端用同一套 token 体系）
- spatie/permission RBAC 复用（角色加 shop_id 维度）

### 需要迁移的部分
| 项 | 迁移方式 |
|---|---|
| `merchants` 表 → `shops` 表 | 一次性数据库迁移 + 代码 grep 替换 |
| 现有 `Mall` 相关空目录 | 按本文档结构重建 |
| 旧 mall 需求文档 | 标注废弃，链接到本文档 |
| 旧 `tenant` 中间件 | 扩展支持子域名识别 shop |

---

## 11. 下一步

1. 你审阅本文档，确认 P0 范围和模块边界
2. 选定剩余待决策项（至少 E/F/G/I）
3. 写 P0 详细技术设计（数据库迁移脚本、API 接口列表、Service 接口）
4. 拉 `feat/mall-mvp` 分支，按 W1 开始实施

---

## 附录 A：Teanary 参考价值清单

```
✅ 模型架构：
  - 每个核心实体的 *Translation 副表设计
  - ProductVariant + Specification 4 层
  - Zone（区域分组）模型
  - Currency + 汇率
  - Promotion 规则引擎（PromotionRule + UserGroup + ProductVariant 多对多）

✅ 工程实践：
  - Snowflake 分布式 ID
  - Service 边界（PaymentService / ShippingService 独立目录）
  - 完整 phpstan + pint + test

✅ 设计原则：
  - AI 辅助而非自动化
  - 关键操作人工确认
  - 不做"黑盒魔法"

⏸️ 暂不采用：
  - Livewire 4（与 Vue SPA + Nuxt SSR 冲突）
  - 多节点同步（P0 单节点足够）
```

## 附录 B：BeikeShop 参考价值清单

```
✅ 模型架构：
  - OrderHistory / OrderPayment / OrderShipment / OrderTotal 拆分
  - Rma + RmaHistory + RmaReason 售后体系
  - TaxClass + TaxRate + TaxRule 三级税费
  - Brand 品牌
  - CMS Page + PageCategory
  - ProductView 浏览统计

✅ 工程实践：
  - 插件系统 plugins/ + Hook + tormjens/eventy 事件驱动
  - 主题系统 themes/（前台外观热换）
  - Repository 模式
  - Admin / AdminAPI / Shop 三层路由分离
  - Socialite + easywechat 集成

✅ 实用集成：
  - GeoIP2（IP 定位）
  - Horizon（队列监控）
  - phpoffice/phpspreadsheet（Excel 导入导出）

⏸️ 暂不采用：
  - Blade 主题（被 Nuxt 替代）
  - JWT-Auth（用 Passport 统一）
  - jQuery + Bootstrap（不符合现代 Vue 生态）
```
