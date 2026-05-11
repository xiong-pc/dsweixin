# gen-module

为 dsweixin 全栈生成完整业务模块，一条命令完成从数据库、后端到前端的全部操作。

## 用法

```
/gen-module <ModuleName> [SQL CREATE TABLE 语句]
```

### 仅模块名（生成骨架，字段待填）
```
/gen-module Log
```

### 模块名 + SQL（全自动，字段全部生成）
```
/gen-module Area
CREATE TABLE `zty_area` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0' COMMENT '父级',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `shortname` varchar(30) NOT NULL DEFAULT '' COMMENT '简称',
  `longitude` varchar(30) NOT NULL DEFAULT '' COMMENT '经度',
  `latitude` varchar(30) NOT NULL DEFAULT '' COMMENT '纬度',
  `level` smallint NOT NULL DEFAULT '0' COMMENT '级别',
  `sort` mediumint NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态1有效',
  PRIMARY KEY (`id`),
  KEY `IDX_nc_area` (`name`,`shortname`),
  KEY `level` (`level`,`sort`,`status`),
  KEY `longitude` (`longitude`,`latitude`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='地址表';
```

---

## 执行步骤

收到指令后，**按顺序**完成以下所有步骤，无需用户中途确认。

---

### 第 0 步：解析参数

- 模块名统一转为 PascalCase（如 `area_log` → `AreaLog`）
- snake_case 用于路由、表名、前端目录（如 `AreaLog` → `area_log`）
- kebab-case 用于 API 路由（如 `AreaLog` → `area-logs`）
- camelCase 用于前端 API 文件名（如 `AreaLog` → `areaLog`）
- 若提供了 SQL，解析所有字段（跳过 `id` 主键），提取：
  - 字段名、MySQL 类型、长度、是否 NOT NULL、DEFAULT 值、COMMENT
- 若未提供 SQL，字段部分留空骨架

**MySQL 类型 → Laravel Migration 方法映射：**

| MySQL 类型                 | Migration 方法                                |
|--------------------------|---------------------------------------------|
| `tinyint`                | `tinyInteger`                               |
| `smallint`               | `smallInteger`                              |
| `mediumint`              | `mediumInteger`                             |
| `int` / `integer`        | `integer` / `unsignedInteger`（看是否 unsigned） |
| `bigint`                 | `bigInteger` / `unsignedBigInteger`         |
| `varchar(n)`             | `string('field', n)`                        |
| `char(n)`                | `char('field', n)`                          |
| `text`                   | `text`                                      |
| `longtext`               | `longText`                                  |
| `decimal(m,d)`           | `decimal('field', m, d)`                    |
| `timestamp` / `datetime` | `timestamp`                                 |

**默认值规则（严格遵守 backend/CLAUDE.md）：**
- 整型字段：NOT NULL → `.default(0)`
- 字符串字段：NOT NULL → `.default('')`
- text / timestamp：→ `.nullable()`（无 default）

**MySQL 类型 → Model $casts 映射：**

| MySQL 类型                                                | cast 类型   |
|---------------------------------------------------------|-----------|
| `tinyint` / `smallint` / `mediumint` / `int` / `bigint` | `integer` |
| `decimal` / `float` / `double`                          | `float`   |
| `varchar` / `char` / `text`                             | 不加 cast   |

**字段 → FormRequest rules 推导：**
- Store：NOT NULL 且无 DEFAULT → `required`；其余 → `nullable`
- Update：NOT NULL 字段 → `sometimes`；其余 → `nullable`
- 字符串加 `max:n`（取 varchar 长度）
- 整型加 `integer`
- status 字段加 `in:0,1`

**字段 → TypeScript 类型映射：**

| MySQL 类型                                                | TS 类型        |
|---------------------------------------------------------|--------------|
| `tinyint` / `smallint` / `mediumint` / `int` / `bigint` | `number`     |
| `varchar` / `char` / `text` / `longtext`                | `string`     |
| `decimal` / `float` / `double`                          | `number`     |
| nullable 字段                                             | `类型 \| null` |

---

### 第 1 步：生成 Controller

路径：`backend/app/Http/Controllers/Api/{ModuleName}Controller.php`

- 继承 `App\Http\Controllers\Api\Controller`
- 构造函数注入 `{ModuleName}Service`
- 实现 `index`、`store`、`show`、`update`、`destroy` 五个方法
- `index` 调用 `$this->paginate()`，`store/show/update/destroy` 调用 `$this->success()`
- Controller 内**禁止**直接引用任何 Model 类
- 文件头注释 + 每个方法注释（按 backend/CLAUDE.md 注释规范）

---

### 第 2 步：生成 Service

路径：`backend/app/Services/Api/{ModuleName}Service.php`

- 实现 `list(array $params)`、`store(array $data)`、`show(int $id)`、`update(int $id, array $data)`、`destroy(int $id)` 方法
- 在此层引用 Model，不对外暴露 Model 实例
- `list` 方法使用 `Model::query()` + `paginate()`，支持常用字段的关键词筛选
- 文件头注释 + 每个方法注释

---

### 第 3 步：生成 FormRequest

路径：
- `backend/app/Http/Requests/Api/{ModuleName}/Store{ModuleName}Request.php`
- `backend/app/Http/Requests/Api/{ModuleName}/Update{ModuleName}Request.php`

- 继承 `App\Http\Requests\Api\ApiFormRequest`
- `authorize()` 返回 `true`
- `rules()` 根据 SQL 字段推导（无 SQL 则留空数组骨架）
- 文件头注释

---

### 第 4 步：生成 Model

路径：`backend/app/Models/{ModuleName}.php`

- 若提供 SQL：填写 `$fillable`（排除 id）、`$casts`（整型字段）
- 原表无 `created_at` / `updated_at` → 设置 `public $timestamps = false`
- 文件头注释

---

### 第 5 步：生成 Migration

路径：`backend/database/migrations/{timestamp}_create_{table_name}_table.php`

- 时间戳格式：`date('Y_m_d_His')`（今天日期）
- 使用 `Schema::create('{snake_plural}', ...)`
- 按 SQL 字段逐一生成 migration 列（跳过 id，用 `$table->increments('id')` 替代）
- 若原表有索引（KEY），用 `$table->index(...)` 对应生成
- 不加 `$table->timestamps()`（除非原表有 created_at / updated_at）

---

### 第 6 步：注册路由

修改 `backend/routes/api.php`：
1. 在文件顶部 use 列表中按字母序插入 `use App\Http\Controllers\Api\{ModuleName}Controller;`
2. 在 `Route::middleware('tenant')->group` 闭包内（租户管理路由之前）追加：

```php
// {模块中文名}管理
Route::apiResource('{kebab-plural}', {ModuleName}Controller::class);
```

> 注意：import 和 route 必须**同一次编辑**写入，避免 pint formatter 将未使用的 import 移除。

---

### 第 7 步：执行迁移

```bash
cd backend && php artisan migrate
```

输出迁移结果，告知用户迁移是否成功。

---

### 第 8 步：写入菜单记录

**8.1 查询父级菜单 ID 及当前子菜单数量**

```bash
php artisan tinker --execute="
echo App\Models\Menu::where('path', '/system')->value('id');
echo ' ';
echo App\Models\Menu::where('parent_id', App\Models\Menu::where('path','/system')->value('id'))->where('type', 2)->count();
"
```

若父菜单不存在，提示用户确认后再继续。

**8.2 插入菜单与按钮权限**

`$sort` = 上一步子菜单数量 + 1

```bash
php artisan tinker --execute="
\$menu = App\Models\Menu::create([
    'parent_id'  => {parentId},
    'name'       => '{模块中文名}管理',
    'type'       => 2,
    'path'       => '{snake_case}',
    'component'  => 'system/{snake_case}/index',
    'icon'       => '{icon}',
    'sort'       => {sort},
    'visible'    => 1,
    'permission' => '',
]);
App\Models\Menu::create(['parent_id' => \$menu->id, 'name' => '{模块中文名}新增', 'type' => 3, 'permission' => 'sys:{snake_case}:add',    'sort' => 1]);
App\Models\Menu::create(['parent_id' => \$menu->id, 'name' => '{模块中文名}编辑', 'type' => 3, 'permission' => 'sys:{snake_case}:edit',   'sort' => 2]);
App\Models\Menu::create(['parent_id' => \$menu->id, 'name' => '{模块中文名}删除', 'type' => 3, 'permission' => 'sys:{snake_case}:delete', 'sort' => 3]);
echo '菜单ID: ' . \$menu->id;
"
```

**图标选择规则**（未指定时按模块名自动匹配）：

| 关键词       | 图标               |
|-----------|------------------|
| user/用户   | `User`           |
| role/角色   | `Avatar`         |
| menu/菜单   | `Menu`           |
| dept/部门   | `OfficeBuilding` |
| dict/字典   | `Collection`     |
| config/配置 | `Tools`          |
| notice/公告 | `Bell`           |
| tenant/租户 | `House`          |
| area/地区   | `Location`       |
| log/日志    | `Document`       |
| 其他        | `Grid`           |

**8.3 同步 MenuSeeder**

在 `backend/database/seeders/MenuSeeder.php` 的 `// Tenant Management` 注释之前插入：

```php
// {ModuleName} Management
${snake_var} = Menu::create([
    'parent_id' => $system->id, 'name' => '{模块中文名}管理', 'type' => 2,
    'path' => '{snake_case}', 'component' => 'system/{snake_case}/index', 'icon' => '{icon}',
    'sort' => {sort}, 'visible' => 1, 'permission' => '',
]);
Menu::create(['parent_id' => ${snake_var}->id, 'name' => '{模块中文名}新增', 'type' => 3, 'permission' => 'sys:{snake_case}:add',    'sort' => 1]);
Menu::create(['parent_id' => ${snake_var}->id, 'name' => '{模块中文名}编辑', 'type' => 3, 'permission' => 'sys:{snake_case}:edit',   'sort' => 2]);
Menu::create(['parent_id' => ${snake_var}->id, 'name' => '{模块中文名}删除', 'type' => 3, 'permission' => 'sys:{snake_case}:delete', 'sort' => 3]);
```

---

### 第 9 步：生成前端文件

#### 9.1 TypeScript 类型定义

路径：`frontend/src/types/api/{camelCase}.ts`

根据 SQL 字段生成：
- `{ModuleName}ListQuery` — 列表查询参数（支持 keywords 及各筛选字段，可空字段加 `?`）
- `Store{ModuleName}Request` — 新增请求体（required 对应 SQL NOT NULL 且无 DEFAULT 字段）
- `Update{ModuleName}Request extends Partial<Store{ModuleName}Request>` — 更新请求体
- `{ModuleName}Item` — 列表/详情返回项（所有字段，nullable 字段加 `| null`）

#### 9.2 API 函数

路径：`frontend/src/api/{camelCase}.ts`

封装 5 个函数，参照以下格式（以 Area 为例）：

```ts
import request from '@/utils/request'
import type { AreaListQuery, StoreAreaRequest, UpdateAreaRequest, AreaItem } from '@/types/api/area'

export function getAreaList(params: AreaListQuery) {
  return request<any, ApiResponse<PageResult<AreaItem>>>({ url: '/areas', method: 'get', params })
}
export function getAreaDetail(id: number) {
  return request<any, ApiResponse<AreaItem>>({ url: `/areas/${id}`, method: 'get' })
}
export function createArea(data: StoreAreaRequest) {
  return request<any, ApiResponse<AreaItem>>({ url: '/areas', method: 'post', data })
}
export function updateArea(id: number, data: UpdateAreaRequest) {
  return request<any, ApiResponse<null>>({ url: `/areas/${id}`, method: 'put', data })
}
export function deleteArea(id: number) {
  return request<any, ApiResponse<null>>({ url: `/areas/${id}`, method: 'delete' })
}
```

URL 使用 kebab-plural（如 `/user-logs`）。

#### 9.3 Vue 页面组件

路径：`frontend/src/views/system/{snake_case}/index.vue`

生成规则：
- **纯日志/只读模块**（模块名含 log/audit/record）：只生成列表 + 搜索 + 删除，**不生成新增/编辑 Dialog**
- **普通 CRUD 模块**：生成列表 + 搜索 + 新增/编辑 Dialog + 删除

通用要求：
- 搜索栏：根据 SQL 字段生成常用筛选项（字符串字段用 `el-input`，枚举/状态字段用 `el-select`）
- 表格列：所有业务字段均展示，`status` 字段用 `el-tag` 渲染
- 按钮权限指令：`v-hasPerm="['sys:{snake_case}:add']"` 等
- 分页：`el-pagination`，参数 `pageNum` / `pageSize`
- `queryParams` 使用 `reactive()`，字面量联合类型字段加 `as` 标注（如 `status: '' as number | ''`）

#### 9.4 TypeScript 类型检查

```bash
cd frontend && npm run type-check
```

零错误后继续，有错误立即修复再继续。

---

### 第 10 步：生成后端 Feature 测试

路径：`backend/tests/Feature/{ModuleName}Test.php`

继承 `Tests\TestCase`，使用 `RefreshDatabase`，`setUp` 中调用 `ensureDefaultTenant()` + `actingAsAdmin()`。

定义私有辅助方法 `create{ModuleName}(array $attrs = [])` 用 Model::create 插入默认完整字段数据。

**必须覆盖的用例：**

| 分组        | 用例                                             | 说明                                     |
|-----------|------------------------------------------------|----------------------------------------|
| GET 列表    | `test_index_returns_paginated_{snake}s`        | 返回结构含 list/total/page/pageSize         |
| GET 列表    | `test_index_filters_by_keywords`               | keywords 能按主要字符串字段模糊过滤                 |
| GET 列表    | `test_index_filters_by_{key_field}`            | 每个重要筛选字段单独一个用例                         |
| GET 列表    | `test_index_pagination_works`                  | pageSize/pageNum 分页正确                  |
| POST 新增   | `test_store_creates_{snake}`                   | 入库 + 响应 data 正确                        |
| POST 新增   | `test_store_validates_required_fields`         | 缺少 required 字段返回 422（有 required 字段时才写） |
| GET 详情    | `test_show_returns_{snake}`                    | 返回正确记录                                 |
| GET 详情    | `test_show_returns_404_for_missing_{snake}`    | 不存在返回 404                              |
| PUT 更新    | `test_update_modifies_{snake}`                 | assertDatabaseHas 验证入库                 |
| PUT 更新    | `test_update_returns_404_for_missing_{snake}`  | 不存在返回 404                              |
| DELETE 删除 | `test_destroy_deletes_{snake}`                 | assertDatabaseMissing 验证删除             |
| DELETE 删除 | `test_destroy_returns_404_for_missing_{snake}` | 不存在返回 404                              |

生成后立即运行，确认全部通过：

```bash
cd backend && php artisan test tests/Feature/{ModuleName}Test.php
```

输出须为 `PASS`，有失败立即修复再继续。

---

### 第 11 步：插入测试数据

通过 tinker 插入 **8~10 条**贴近真实场景的测试数据：

```bash
php artisan tinker --execute="
\$rows = [
    // 根据表字段生成有意义的测试数据
    // 字符串字段填中文/英文有意义值，整型字段用合理范围的数字
    // 至少覆盖 status=0 和 status=1 两种状态（如有 status 字段）
];
foreach (\$rows as \$row) {
    App\Models\{ModuleName}::create(\$row);
}
echo '已插入 ' . count(\$rows) . ' 条测试数据';
"
```

---

### 第 12 步：同步接口文档

在 `docs/api.md` 中两处同步（文件不存在则跳过并提示）：

**11.1 目录**：在 `- [错误码说明]` 之前追加：
```
- [{模块中文名}管理](#{anchor})
```

**11.2 正文**：在 `## 错误码说明` 之前插入完整章节，包含：
- 章节标题 `## {模块中文名}管理`
- 说明行：`> 所有接口需认证 + 租户中间件（auth:api + tenant）`
- 5 个接口：`GET /{kebab-plural}`、`POST`、`GET /{id}`、`PUT /{id}`、`DELETE /{id}`
- 每个接口：参数/请求体表格 + 成功响应 JSON 示例

---

### 第 14 步：同步数据库文档

在 `docs/database.md` 中两处同步（文件不存在则跳过并提示）：

**12.1 目录表格**：在业务表列表末行追加：
```
| [{table_name}](#{anchor}) | {模块说明} |
```

**12.2 表详情**：在 `## 中间关联表详情` 之前插入，包含：
- 表用途说明（1-2 句）
- 字段明细表（字段、数据类型、可空、默认值、说明）
- 多租户隔离说明
- 索引表

---

### 第 15 步：汇总输出

完成后输出：

**新建文件（10 个）：**
| 类型 | 路径 |
|------|------|
| Controller | `backend/app/Http/Controllers/Api/{ModuleName}Controller.php` |
| Service | `backend/app/Services/Api/{ModuleName}Service.php` |
| StoreRequest | `backend/app/Http/Requests/Api/{ModuleName}/Store{ModuleName}Request.php` |
| UpdateRequest | `backend/app/Http/Requests/Api/{ModuleName}/Update{ModuleName}Request.php` |
| Model | `backend/app/Models/{ModuleName}.php` |
| Migration | `backend/database/migrations/{timestamp}_create_{table_plural}_table.php` |
| Feature Test | `backend/tests/Feature/{ModuleName}Test.php` |
| TS 类型 | `frontend/src/types/api/{camelCase}.ts` |
| API 函数 | `frontend/src/api/{camelCase}.ts` |
| Vue 页面 | `frontend/src/views/system/{snake_case}/index.vue` |

**修改文件（4 个）：**
- `backend/routes/api.php`
- `backend/database/seeders/MenuSeeder.php`
- `docs/api.md`（若存在）
- `docs/database.md`（若存在）

**测试结果：**
- 迁移输出
- Feature Test：X passed (Y assertions)
- type-check：零错误
- 测试数据插入条数

**菜单：**
- 菜单 ID + 3 个按钮权限标识（`sys:{snake}:add/edit/delete`）

**API 路由：**
```
GET    /api/v1/{kebab-plural}
POST   /api/v1/{kebab-plural}
GET    /api/v1/{kebab-plural}/{id}
PUT    /api/v1/{kebab-plural}/{id}
DELETE /api/v1/{kebab-plural}/{id}
```

> 重新登录后菜单即可在前端侧边栏出现。

---

## 注意事项

- 所有后端文件头部加注释（`@Author: xiong-pc` / `@Email: 562740366@qq.com` / `@Date: 今天日期` / `@Version: 1.0.0`）
- 今天日期从 CLAUDE.md `currentDate` 获取，格式 `Y-m-d H:i:s`
- 若文件已存在则跳过并提示用户，不覆盖
- SQL 中的表名前缀（如 `zty_`）去掉，只取语义名称生成 Laravel 表名
- 路由注册时 import 和 Route 必须同一次编辑写入，避免 pint 移除未使用 import
- TypeScript 禁止使用 `any` 类型；`reactive()` 中联合类型字段必须用 `as` 标注
