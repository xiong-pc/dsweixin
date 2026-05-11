# 商城业务视图（mall）

> 占位目录，尚无 .vue 文件入驻。此 README 用于说明目录用途与规划，防止同事误以为忘了建或目录空就可删除。

## 目录用途

统一存放商城（电商 + 配送 + 订单）相关的业务视图，形成与后端命名空间、API 路由的三层对称：

| 层 | 位置 / 命名 | 对称示例 |
|---|---|---|
| 前端视图 | `frontend/src/views/mall/` | `views/mall/product/category/index.vue` |
| 后端控制器（规划） | `App\Http\Controllers\Api\Mall\` | `App\Http\Controllers\Api\Mall\ProductCategoryController` |
| API 路由（规划） | `/api/v1/mall/*` | `GET /api/v1/mall/product/categories` |

## 规划的子模块（按 specs 优先级排序）

参见 `docs/superpowers/specs/2026-04-24-mall-system-requirements.md`。

```
mall/
├── product/
│   ├── category/         # 商品分类（树形）
│   ├── list/             # 商品列表 + SKU
│   └── stock/            # 库存管理 + 预警
├── order/
│   ├── list/             # 订单列表（全部/待付款/待发货/...）
│   └── refund/           # 退款申请
├── delivery/
│   ├── man/              # 配送员管理
│   └── order/            # 配送单管理
└── dashboard/            # 商城 Dashboard（最后做）
```

## 当前状态

- 本目录**仅有本 README，暂无 .vue 文件**
- 相关视图位置约定（按 specs 落地）：
  - `mall/product/category/index.vue` → 代替现有 `views/product/category/index.vue`
  - `mall/product/list/index.vue`、`mall/product/stock/index.vue` 等为新建
- **现有 `frontend/src/views/product/category/index.vue` 暂未迁入 `mall/product/category/`**。
  迁移方案见下方"已有 product/ 的迁移策略"

## 已有 product/ 的迁移策略

`frontend/src/views/product/category/index.vue` 已在生产运行，贸然 `git mv` 到 `mall/product/category/` 会同时触动：

- 前端 api 模块 `frontend/src/api/category.ts` 的 url（若后端也加 `/api/v1/mall/` 前缀）
- 后端 `ProductCategoryController` 的命名空间（应进 `Api\Mall\`）
- `MenuSeeder` 中商品分类菜单 `component` 字段
- 生产 DB `menus` 表已写入的 `component` / 路径

因此约定：**待第一个 mall 业务开发任务启动时一并迁移**，不在本次结构整理（#7 保守方案）中处理。

## 建目录约定

同事新建 mall 子模块时：

- 视图目录层级对应 specs 中的前端路由（`/mall/product/category` → `mall/product/category/index.vue`）
- 在本 README 的"规划"表里打勾 / 补链接，保持导航性
- 后端同步建 `App\Http\Controllers\Api\Mall\` 命名空间控制器 + `/api/v1/mall/` 路由
