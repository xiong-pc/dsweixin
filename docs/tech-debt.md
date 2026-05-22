# 技术债清单（tech-debt）

> 整理时间：2026-05-12
> 整理依据：完成 #6 + #7 API 命名空间重构后的全项目体检
> 目的：把"模糊感觉项目还能更好"变成"明确可执行的任务条目"

## 现状基线

- **后端**：14 Controllers / 12 Services / 12 Models / 22 migrations / 12 Feature tests / 1 Unit test
- **前端**：24 Vue components / 12 API modules / 5 stores / 14 type defs
- **测试**：`php artisan test` 130 passed (362 assertions)，5s 跑完
- **构建**：`npm run build` 14s，主包 1.27MB（未分包）
- **CI/CD**：无
- **lint/format**：无

---

## 🔴 高优先级（建议两周内完成）

### TD-01 加 CI/CD

**现状**：根目录无 `.github/workflows/`。每次合并前 130 个测试靠手动跑，PR 没自动护栏。

**痛点**：
- 重构如 #6 / #7 容易 regression，靠人记得跑测试
- 协作时新人 PR 没自动反馈
- 已有的 `php artisan test` + `npm run build` + `npm run type-check` 都没自动化

**落地步骤**：

1. 新建 `.github/workflows/ci.yml`：

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  backend:
    name: Backend (PHP 8.3 + Laravel)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_sqlite, sqlite3, mbstring, openssl, fileinfo
          coverage: none
          tools: composer:v2
      - working-directory: backend
        run: composer install --prefer-dist --no-progress --no-interaction
      - working-directory: backend
        run: cp .env.example .env && php artisan key:generate
      - working-directory: backend
        run: php artisan test

  frontend:
    name: Frontend (Node 20 + Vue 3)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json
      - working-directory: frontend
        run: npm ci
      - working-directory: frontend
        run: npm run type-check
      - working-directory: frontend
        run: npm run build
```

**关键设计**：
- 测试用内存 SQLite（`phpunit.xml` 强制 `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`），
  **不需要起 MySQL 服务**
- backend / frontend 两个 job **并行**跑，缩短反馈时间
- `concurrency` 控制：同一 PR 多次推送时取消旧 run，节省 GitHub Actions minutes
- frontend 配 `cache: 'npm'`，二次跑 install 走缓存

2. 验证：开个空 PR，看 Actions tab 是否亮绿
3. 给主分支加保护规则：Settings → Branches → main → Require status checks

**工作量**：1-2 小时
**风险**：低（只是新增文件）
**前置依赖**：无

**已完成**：2026-05-12（实际无需 MySQL 服务，比原模板更简洁）

---

### TD-02 加 lint/format 工具链

**现状**：
- 前端 `package.json` 只有 `vue-tsc`，没 `eslint`/`prettier`
- 后端 `composer.json` 已装 `laravel/pint`（默认 Laravel preset），但**没接入 CI**；
  `larastan`（静态分析）未装

**痛点**：
- 代码风格全靠自觉，commit diff 噪音多（空格、引号、缩进）
- 静态错误（未定义变量、类型不匹配）只能等运行时才发现

**落地步骤**：

后端：

```bash
# backend/
composer require --dev laravel/pint larastan/larastan
./vendor/bin/pint                              # 一次性格式化全部代码（会改文件，单独 commit）
./vendor/bin/phpstan analyse --memory-limit=2G # level 5 起步，逐步提到 8
```

新建 `backend/phpstan.neon`：

```neon
includes:
  - vendor/larastan/larastan/extension.neon
parameters:
  paths: [app, tests]
  level: 5
  ignoreErrors: []
```

前端：

```bash
# frontend/
npm install --save-dev eslint @vue/eslint-config-typescript eslint-plugin-vue prettier eslint-config-prettier
npx eslint --init
npm run lint -- --fix  # 一次性 fix 全部
```

`package.json` 加 scripts：

```json
{
  "lint": "eslint . --ext .vue,.ts,.tsx",
  "format": "prettier --write \"src/**/*.{ts,vue,json,md}\""
}
```

3. 把 lint 步骤加进 TD-01 的 CI workflow

**工作量**：半天（含一次性格式化全代码 + 修少量 lint 报错）
**风险**：中（一次性 format 会改大量文件，需单独 commit + 团队 rebase）
**前置依赖**：建议先 TD-01

**已完成**：2026-05-12

实施记录：
- 后端：`pint` 一次性 format 70 文件（440 lines diff，纯样式），单独 commit 隔离 git blame
- 前端：装 `eslint@10` flat config + `prettier@3` + Vue/TS 预设
  - `npm run format` 修 52 文件（840 lines diff）
  - 修 11 个 ESLint errors：9 个 empty interface 改 type alias、`el.parentNode?.removeChild`、
    `catch {}` 无参数化
  - 自定义规则：`vue/multi-word-component-names: off`（项目 views/feature/index.vue 结构决定）、
    `@typescript-eslint/no-explicit-any: warn`（存量 99 处 any 渐进改进，不阻塞 CI）
- CI 集成：
  - backend job 加 `vendor/bin/pint --test`（在跑测试前）
  - frontend job 加 `npm run format:check` + `npm run lint:check`（在 type-check 前）

配套子项见 TD-02b（已独立成章节）。

---

### TD-02b larastan 静态分析

**现状**：PHP 代码没有静态类型分析。`pint` 只管风格，无法发现 null safety、类型错误、
未定义属性等类型层面问题。

**痛点**：
- Laravel Resource、Eloquent Relation 等魔术属性写错也不报错（只能等运行时）
- 类型签名不一致只能靠人肉 review

**落地步骤**：

1. 安装 larastan（PHPStan + Laravel 扩展）：

```bash
composer require --dev larastan/larastan
```

> **注意**：项目当前 `laravel/passport ^13.7` 有 PKSA-wc55-9qj2-7v4h 等 3 个 audit 公告，
> 装 larastan 会被阻挡。已在 `composer.json` 的 `config.audit.ignore` 加豁免（待官方
> 发版后移除）。

2. 创建 `backend/phpstan.neon`（level 5 起步）：

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan-baseline.neon

parameters:
    level: 5
    paths: [app/]
    excludePaths:
        - app/Console/Commands/CodeGenerator.php
```

3. 首次跑生成 baseline（锁定历史错误）：

```bash
./vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline=phpstan-baseline.neon
```

4. CI workflow backend job 加 phpstan 步骤（位于 pint 之后、test 之前）

5. composer.json 加 scripts：
   - `composer stan` 跑分析
   - `composer stan:baseline` 修一批错后重新生成 baseline

**实测**：
- level 5 跑出 **46 errors**，主要类别：
  - Laravel Resource 子类访问未声明属性（`$this->id`/`$this->name` 等）——最多
  - `Passport ScopeAuthorizable::revoke()` 类型不识别
  - `User::$tenant` 等 relation 没 phpdoc
  - 部分 `new static()` 不安全用法
- 全部锁入 baseline，CI 立即可过；新增代码错误仍会被报告

**渐进消化策略**：
- 每个 PR 顺便修 1-2 个 baseline 错误（重新跑 `composer stan:baseline`）
- 主要修法：给 Resource 加 `@property-read` phpdoc，或改 `$this->resource->xxx`
- 等所有 Resource 修完再考虑提到 level 6/7

**已完成**：2026-05-12

实施记录：
- 安装：larastan ^3.9（含 phpstan ^2.x）
- 配置：phpstan.neon level 5 + phpstan-baseline.neon（46 errors 锁定）
- CI 集成：backend job 加 phpstan analyse 步骤
- composer.json：加 `stan` / `stan:baseline` scripts + 3 个 audit ignore（passport/phpseclib 间接依赖）
- 验证：`./vendor/bin/phpstan analyse` → OK No errors（baseline 锁住后）

**工作量**：半天（含装 + 生成 baseline + CI 接入）
**风险**：低（baseline 模式）
**前置依赖**：TD-01（CI 集成）

---

### TD-03 补 Unit 测试（持续进行中）

**现状**：只有 1 个 `Tests\Unit\ExampleTest`，所有业务逻辑都靠 Feature test。

**痛点**：
- Feature test 慢（每个 5s 跑完 130 个，平均 38ms/test，但都要走 HTTP + DB）
- 纯逻辑（如菜单树构建、路由过滤）应该单元测试
- 重构 Service 时无 lockdown，回归靠 Feature test 间接覆盖

**落地模式（本次起步建立）**：

1. 识别 Service 中的**纯函数**（不依赖 $this 状态，不依赖 DB/auth/HTTP）
2. 重构为 `public static`（0 对外调用方的情况下，侵入最小）
3. 在 `tests/Unit/Services/` 下建同名测试，用 `PHPUnit\Framework\TestCase`（不加载 Laravel app）
4. 每个 public 方法至少覆盖：空输入、单项、边界条件、嵌套/递归（若适用）

**已完成起步（2026-05-12）**：

| 测试文件 | 被测目标 | cases | 范式 |
|---|---|---|---|
| `tests/Unit/Services/MenuServiceTest.php` | `MenuService::buildTree` | 8 | 纯数组递归构建树 |
| `tests/Unit/Services/AuthServiceTest.php` | `AuthService::buildMenuTree` | 8 | 对象路由树（stdClass 模拟） |
| `tests/Unit/Exceptions/BusinessExceptionTest.php` | `BusinessException` | 4 | 异常值对象 |

**Unit test 性能验证**：
- Unit: 21 tests / 0.58s = ~28ms/test（不加载 Laravel app）
- Feature: 130 tests / 5.69s = ~44ms/test（建 DB schema + HTTP round-trip）
- Unit 比 Feature 快约 40%，主要节省是 Laravel 启动 + DB 迁移

**配套小重构（为可测性）**：
- `MenuService::buildTree`：`private` → `public static`
- `AuthService::buildMenuTree`：`private` → `public static`
- 两者都是无状态纯函数，静态化更准确表达语义，且 Unit test 可以直接调用
- 调用方改 `$this->` → `self::`（0 个外部调用方，零风险）

**后续持续补**（不必立刻做完，业务新增时同步加）：

- [ ] `AuthService::attemptLogin`：密码校验正负路径（用 User mock）
- [ ] `AuthService::resolvePermissions`：SUPER_ADMIN 权限 + 按钮权限收集
- [ ] `UserService::hashPassword`（如果有）
- [ ] 任何未来新增的纯逻辑 Service 方法

**工作量**：持续投入，每周 1-2 小时同步补
**风险**：低
**前置依赖**：无

**已起步**：2026-05-12（+ 20 cases / +45 assertions，建立范式）

---

## 🟡 中优先级（一个月内完成）

### TD-04 拆前端 bundle

**现状**：`npm run build` 产物 `dist/assets/index-CDXWtXNX.js` 1.27MB，超 Vite 默认 500KB 警告阈值。

**痛点**：
- 用户首屏要拉 1.27MB JS，弱网下卡顿
- vendor（element-plus、vue、vue-router、pinia 等）跟业务代码混在一起，业务改一行就要全量重新缓存

**落地步骤**：

修改 `frontend/vite.config.ts`（示例基于本项目实际 dependencies）：

```ts
build: {
  rollupOptions: {
    output: {
      manualChunks: {
        'vue-vendor': ['vue', 'vue-router', 'pinia', '@vueuse/core'],
        'element-vendor': ['element-plus', '@element-plus/icons-vue'],
        'utils-vendor': ['axios', 'nprogress', 'vue-i18n'],
      },
    },
  },
  chunkSizeWarningLimit: 600,
}
```

验证：
```bash
npm run build
# 实测：
#   业务主包 index.js     15.88 kB (gzip 6.7 kB)   ← 从 1,274 kB 降 99%
#   vue-vendor           120.50 kB (gzip 46.7 kB)
#   utils-vendor         104.94 kB (gzip 36.5 kB)
#   element-vendor     1,030.31 kB (gzip 325 kB)   ← 仍是大头，见 TD-04b
```

**重要说明**：本项仅改善**缓存策略**（改业务代码不再使 vendor 缓存失效），
**首屏总下载量未减**。要真正减首屏，需配合 **TD-04b 按需引入**。

**工作量**：1 小时
**风险**：低（纯 Vite 配置）
**前置依赖**：无

**已完成**：2026-05-12（首次入仓同一 commit）

---

### TD-04b 前端按需引入 element-plus

**现状**：TD-04 做完后，`element-vendor-*.js` 仍达 1,030 kB（gzip 325 kB），
根源是 `frontend/src/plugins/index.ts` 全量 import：

```ts
import ElementPlus from 'element-plus';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
```

**痛点**：用户首屏即便只打开登录页，也要拉 element-plus 全量 1MB。

**落地步骤**：

```bash
npm install --save-dev unplugin-vue-components unplugin-auto-import
```

`frontend/vite.config.ts`：

```ts
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

plugins: [
  vue(),
  AutoImport({ resolvers: [ElementPlusResolver()] }),
  Components({ resolvers: [ElementPlusResolver()] }),
],
```

删除 `frontend/src/plugins/index.ts` 里的全量 `import ElementPlus`（组件）和 
`import * as ElementPlusIconsVue`（图标按需单独处理）。

**预期 vs 实测**：

| 维度 | 预期 | 实测 |
|---|---|---|
| `element-vendor.js` | 1MB → 300-500 kB | **1,030 → 1,029 kB（几乎无变化）** |
| element-plus CSS | 全量 ~350 kB | **38 个按需 CSS 文件，单页只加载用到的（首屏 ~15 kB gzip）** |
| 工具链 | - | 模板写 `<el-button>` 自动 import + 按需 CSS |

**JS 未减小的根本原因**：项目实际使用了 39 个 el-* 组件（见 `frontend/src/types/components.d.ts`），
包含 `ElTable` / `ElDatePicker` / `ElSelect` / `ElTree` / `ElPagination` 等"大组件"，39 个加起来 1MB 合理。
构建层面已经只打这 39 个，要继续瘦身只能业务层减少组件使用，不是构建优化能解决。

**真实收益**：
- 首屏 CSS 由全量 ~110 kB gzip → ~15 kB gzip，**减约 95 kB gzip**
- 路由切换时再加载对应页面的 el-* CSS，提升整体响应感
- 开发心智简化：模板里 `<el-button>` 即可，无需在 plugins/ 维护全量 import

**进阶方向（未做，可作 TD-04c）**：把 `element-vendor` 拆为多 chunk，按路由 lazy load 组件级 JS，
首屏 JS 才能真减；改动复杂、维护成本高、收益要做实验验证

**工作量**：30min - 1h（含验证所有页面组件渲染正常）
**风险**：中（需全页面回归，确保没有遗漏 `ElMessage` / `ElLoading` 等编程式组件）
**前置依赖**：建议在 TD-04 完成后做

**已完成**：2026-05-12（CSS 按需生效，JS 维度受业务组件使用数限制）

---

### TD-05 补测试覆盖率

**现状**（修正）：`phpunit.xml` 已用 PHPUnit 11 新版 `<source>` 节点定义覆盖率范围，
不是旧版 `<coverage>` 节点。**真正缺的是 CI 跑 coverage + 设最低门槛**。

**落地步骤**：

1. `phpunit.xml` 在 `<source>` 加 `<exclude>` 排除非业务代码：

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
    <exclude>
        <!-- Console 命令是开发工具，不在 Feature test 范围 -->
        <directory>app/Console</directory>
    </exclude>
</source>
```

2. CI 上启用 PCOV（比 Xdebug 快 10x，仅用于覆盖率收集）：

```yaml
# .github/workflows/ci.yml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.3'
    coverage: pcov  # ← 改这里

- name: Run tests with coverage
  working-directory: backend
  run: php artisan test --coverage --min=30
```

3. composer.json 加 `test:coverage` script 方便本地跑（前提：本地装 PCOV/Xdebug）

**工作量**：半天
**风险**：低
**前置依赖**：TD-01（CI 集成）

**已完成**：2026-05-12

实施记录：
- `phpunit.xml` 加 exclude `app/Console`（避免 319 行的 CodeGenerator.php 拉低 baseline）
- CI workflow `coverage: none` → `coverage: pcov`，跑 `--coverage --min=30`
- composer.json 加 `test:coverage` script
- **--min=30 是保守起步值**（本地无 PCOV/Xdebug 无法测真实 baseline，
  130 个 Feature test 实际覆盖率应远高于此）

**待办（首次 CI 跑出 baseline 后）**：
- 看 GitHub Actions 实际覆盖率
- 把 `--min=30` 上调至 `baseline - 5%`（作为护栏防倒退）
- 不接 Codecov（避免增加外部依赖，CI 日志直接看覆盖率即可）

**进阶方向（可选）**：
- 接 Codecov：申请 token、加 codecov-action，PR 自动出覆盖率 diff 评论
- 装 Xdebug 本地用：`pecl install xdebug` + 配 php.ini `xdebug.mode=coverage`

---

### TD-06 Service 加 interface 抽象

**现状**：12 个 Service 都是具体类，Controller 直接 `new XxxService` 或注入具体类型。

**痛点**：
- 想给 Service 加缓存装饰、远程调用、Mock 测试时改动面大
- 单元测试想 mock Service 必须用 Mockery 改对象

**落地步骤**（按需做，不必一次全改）：

1. 高频改动的 Service 加 interface，例如：

```php
// app/Contracts/Api/AuthServiceContract.php
interface AuthServiceContract {
    public function login(array $credentials): array;
    public function getRouteTree(User $user): array;
}

// app/Services/Api/AuthService.php
class AuthService implements AuthServiceContract { ... }

// app/Providers/AppServiceProvider.php::register()
$this->app->bind(AuthServiceContract::class, AuthService::class);
```

2. Controller 改为依赖 interface：

```php
public function __construct(private AuthServiceContract $service) {}
```

**工作量**：每个 Service ~30 分钟，按需挑做
**风险**：低
**前置依赖**：无

---

## 🟢 低优先级（有空时再做）

### TD-07 加 commit 规范工具

**现状**：commit message 全靠自觉，已经在用 conventional commits 风格（refactor: / fix: / docs:）但没强约束。

**落地步骤**：

```bash
# 根目录
npm install --save-dev @commitlint/cli @commitlint/config-conventional husky lint-staged
npx husky init
echo "npx --no -- commitlint --edit \$1" > .husky/commit-msg
```

`commitlint.config.js`：

```js
module.exports = { extends: ['@commitlint/config-conventional'] };
```

**工作量**：1 小时
**风险**：低

---

### TD-08 加 OpenAPI/Swagger

**现状**：`docs/api.md` 是手写的，前后端类型对齐靠人肉同步（`frontend/src/types/api/*.ts` 是手抄后端响应结构）。

**落地步骤**：

后端用 [scramble](https://github.com/dedoc/scramble)（Laravel 自动生成 OpenAPI）：

```bash
composer require dedoc/scramble
# 访问 /docs/api 看自动生成的 OpenAPI 文档
```

进阶：前端用 `openapi-typescript` 从 OpenAPI 自动生成 `types/api/*.ts`，废弃手写类型。

**工作量**：1 天（含调通自动生成 + 替换手写类型）
**风险**：中（前端类型变化大，需充分回归）

---

### TD-09 监控 / 日志聚合

**现状**：生产环境出问题只能 ssh 看 `backend/storage/logs/laravel.log`。

**落地步骤**：

- 后端：接 Sentry / Bugsnag（`composer require sentry/sentry-laravel`）
- 前端：接 Sentry browser SDK
- 日志：Filebeat → ELK 或直接 Loki + Grafana

**工作量**：半天到 1 天
**风险**：低
**前置依赖**：无（但需要有可访问的监控服务）

---

## 推荐执行顺序

按"价值密度 / 工作量"排序：

1. ✅ **TD-04 拆前端 bundle**（1h，缓存策略立刻见效）
2. ✅ **TD-04b element-plus 按需引入**（30min-1h，CSS 首屏减 95KB gzip）
3. ✅ **TD-01 加 CI/CD**（2h，护栏价值高）
4. ✅ **TD-02 加 lint/format**（半天，配合 CI）
5. ✅ **TD-05 补测试覆盖率**（半天，配合 CI）
6. 🟨 **TD-03 补 Unit 测试**（已起步，20 cases；持续在新业务时同步补）
7. ✅ **TD-02b larastan 静态分析**（半天，baseline 锁 46 errors，渐进消化）
8. **TD-06 Service interface**（按需）← 业务侧没遇到痛点前可延后
9. **TD-07 commit 规范**（1h，团队 >2 人时再做）
10. **TD-08 OpenAPI**（开始多人协作或多端调用时再做）
11. **TD-09 监控**（上生产前必做）

## P0 跨境电商 SaaS · 进度看板

> 配套规划：[`superpowers/specs/2026-05-12-cross-border-mall-saas-plan.md`](./superpowers/specs/2026-05-12-cross-border-mall-saas-plan.md)
>
> 详细任务：[`superpowers/specs/2026-05-12-cross-border-mall-saas-p0-tasks.md`](./superpowers/specs/2026-05-12-cross-border-mall-saas-p0-tasks.md)

### 状态图例

⬜ 未开始 · 🟨 进行中 · ✅ 已完成 · ⏸️ 阻塞 · ❌ 已取消

### 进度统计

| 总数 | 已完成 | 进行中 | 阻塞 | 完成率 |
|---|---|---|---|---|
| 47 | 39 | 0 | 0 | 83.0 % |

### 进度明细

| 状态 | PR | 模块 | 工日 | 完成日期 | commit | 备注 |
|---|---|---|---|---|---|---|
| ✅ | M01-PR1 tenants 扩字段 | M01 | 0.5 | 2026-05-13 | `8b0de8a` | 5 新字段，7 测试全过，pint+stan 全绿 |
| ✅ | M01-PR2 新建 shops 表 | M01 | 1.0 | 2026-05-13 | `112409a` | 全新表（无 merchants 历史包袱），16 测试，subdomain 全局唯一 |
| ✅ | M01-PR3 ShopResolverMiddleware | M01 | 0.5 | 2026-05-13 | `624b674` | 新建独立中间件（13 测试），host + X-Shop-Subdomain header 两种解析方式 |
| ✅ | M01-PR4 plans 套餐表 | M01 | 1.0 | 2026-05-13 | `230c323` | 额度字段 + Seeder 三档 + 16 测试，**M01 模块 100%** |
| ✅ | M02-PR5 lang/currency/country | M02 | 1.0 | 2026-05-13 | `6c9f66a` | 4 张表 + I18nSeeder（13 语/11 币/28 国）+ 24 测试 |
| ✅ | M02-PR6 zones 区域分组 | M02 | 0.5 | 2026-05-13 | `6d4cff7` | zones + zone_countries pivot，11 测试（与 PR7 合包）|
| ✅ | M02-PR7 exchange_rates | M02 | 0.5 | 2026-05-13 | `6d4cff7` | rate(18,8)，同步 Job + everySixHours schedule，15 测试，**M02 模块 100%** |
| ✅ | M03-PR8 specifications | M03 | 1.0 | 2026-05-13 | `624bea4` | 4 表 + HasTranslations trait (复用) + 23 测试，启动 Mall 命名空间 |
| ✅ | M03-PR9 attributes | M03 | 1.0 | 2026-05-13 | `df331fb` | 4 表（不含 color_hex），验证 HasTranslations trait 跨模型复用，16 测试 |
| ✅ | M03-PR10 products 主表 | M03 | 1.5 | 2026-05-13 | `300b694` | 13 字段 + SoftDeletes + ProductTranslation（8 字段含 SEO），slug 唯一性（tenant+shop+locale），22 测试 |
| ✅ | M03-PR11 product_variants | M03 | 1.0 | 2026-05-13 | `6e2f575` | SKU + pivot + matrix 生成器，sku 全局唯一，available_stock accessor，21 测试 |
| ⬜ | M03-PR12 后台商品 UI 基础 | M03 | 1.0 | | | + TranslationTabs 组件 |
| ⬜ | M03-PR13 后台变体 UI | M03 | 1.0 | | | 矩阵生成 + 批量编辑 |
| ✅ | M03-PR14 简单商品快速创建 | M03 | 0.5 | 2026-05-13 | `f463b8b` | POST /products/quick-create 一次建 SPU+默认 SKU，完全事务原子，8 测试，**M03 后端 100%** |
| ✅ | M04-PR15 categories 树 | M04 | 1.5 | 2026-05-13 | `dcb54b9` | parent_id 树 + reorder 拖拽 + cycle 防循环 + multi-field 翻译（name+description），18 测试 |
| ✅ | M04-PR16 brands | M04 | 0.5 | 2026-05-13 | `37f98eb` | brands + brand_translations（含 description），复用 saveTranslations 多字段模式，13 测试，**M04 后端 100%** |
| ⬜ | M04-PR17 商品筛选联动 UI | M04 | 1.0 | | | |
| ✅ | M05-PR18 carts | M05 | 1.0 | 2026-05-13 | `997b8f6` | 4 身份场景（游客/登录/合并/locale），header 驱动身份，22 测试，启动 Shop 前台 API |
| ✅ | M05-PR19 orders | M05 | 1.5 | 2026-05-13 | `3519be2` | 3 表 + OrderStatus enum + createFromCart + 快照（name/sku/image/spec/price）+ 状态机，19 测试 |
| ✅ | M05-PR20 库存预占 | M05 | 1.0 | 2026-05-13 | `65a5ed6` | InventoryService（lockForUpdate防超卖）+ cancelOrder/confirmPayment + everyMinute Job + 20 测试 |
| ✅ | M05-PR21 价格三段式 | M05 | 0.5 | 2026-05-18 | `d647ae1` | PriceCalculator（base × markup% × 汇率）+ tenants.price_markup_pct + OrderService 集成，23 测试 |
| ✅ | M05-PR22 前台下单 API | M05 | 1.0 | 2026-05-18 | `ad06976` | GET preview + POST place-order，CheckoutFlowTest 9 测试，preview→place 数值一致性验证，**M05 模块 100%** |
| ✅ | M06-PR23 支付驱动抽象 | M06 | 1.0 | 2026-05-18 | `efec13c` | payment_methods 表 + 3 DTO + PaymentDriverInterface + AbstractPaymentDriver + PaymentManager（shop>tenant 优先，extend 可热插）+ 13 单元测试，预留 Stripe/Wechat 接入点 |
| ✅ | M06-PR24 StripeDriver | M06 | 1.5 | 2026-05-18 | `4e27f9a` | StripeDriver + StripeClient 接口抽象 SDK + PaymentWebhookController + 4 事件类型解析（checkout.session.completed/payment_intent.*/charge.refunded）+ zero-decimal 货币处理（JPY/KRW）+ 20 测试（FakeStripeClient + 幂等 + 签名失败）。composer require stripe/stripe-php@20.1 |
| ✅ | M06-PR25 WechatDriver | M06 | 1.5 | 2026-05-18 | `8dd441a` | WechatDriver（jsapi/h5/native 三场景）+ WechatClient 接口抽象 yansongda/pay v3 SDK + 复用 PaymentWebhookController + V3 异步通知（TRANSACTION.SUCCESS / REFUND.SUCCESS）解密 + 元→分单位换算 + 18 测试（FakeWechatClient + 幂等 + 签名失败 + scene_info/payer.openid 校验）。composer require yansongda/pay@^3.7 |
| ✅ | M06-PR26 order_payments | M06 | 0.5 | 2026-05-18 | `b350eb9` | order_payments 表 + OrderPaymentStatus enum + OrderPaidEvent + HandleOrderPaidListener（复用 OrderService.confirmPayment）+ 8 测试。**调整依赖顺序**：原 spec PR26 在 PR24/25 后，现提前以解环形依赖（PR24 需写 order_payments + 发 OrderPaidEvent） |
| ✅ | M06-PR27 退款链路 | M06 | 0.5 | 2026-05-19 | `e10016c` | RefundService（PaymentManager 派发 driver.refund + DB 事务外调网关）+ InventoryService::restore（stock += qty）+ 全/部分退款分支（全额翻订单 Refunded + 还库存；部分仅写流水）+ 25 测试（StripeRefundTest 13 + WechatRefundTest 12，覆盖 SUCCEEDED/PENDING/FAILED、JPY zero-decimal、状态机边界、partial 不变状态）+ 6 个 lang key。**M06 模块 100% 收官** |
| ✅ | M07-PR28 shipping_methods | M07 | 1.0 | 2026-05-19 | `0938380` | 3 张迁移（shipping_methods + shipping_method_translations + shipping_rates）+ 复用 zones（M02-PR6）+ ShippingMethodService（嵌套保存 translations + rates，update 整体替换）+ ShippingMethodController CRUD + 校验（zone 必填存在、weight_max=0 视为无上限）+ 16 测试（多租户隔离 + rates 替换 + 软删除 + 关键字过滤）+ 3 个 lang key |
| ✅ | M07-PR29 运费计算 | M07 | 1.0 | 2026-05-19 | `44e8aa2` | ShippingService::quote(Cart, country) → 候选 method 列表（fee/is_free/weight_g/rate_id）+ calculate(method_id) 单查；一国可属多 zone 取并集；重量单位 g/kg/oz/lb 自动转化；weight_max=0 无上限；free_threshold 命中费零（复用 PriceCalculator 算 subtotal）；禁用 method / 跨租户 / cart 空 / zone 未覆盖 → 排除。**ShippingCalculateTest 18 case** |
| ✅ | M07-PR30 order_shipments | M07 | 1.0 | 2026-05-19 | `1156f4c` | order_shipments 表 + OrderShipmentStatus enum（shipped/delivered/cancelled）+ OrderShipmentService::ship 推进 Paid→Shipped + markDelivered 全部签收转 Delivered + cancel 撤销；拆单可多 shipment；Shop OrderResource 暴露 shipments[] 供客户查询。**OrderShipmentTest 13 case**，4 个 lang key。**M07 模块 100% 收官** |
| ✅ | M08-PR31 订单状态机 | M08 | 1.0 | 2026-05-19 | `c7b0caa` | order_histories 表 + OrderStateMachine（canTransition / assertCanTransition / nextStates）+ OrderObserver 自动写 history（静态 spl_object_id context 表透传 reason/operator）+ OrderService::transitionStatus 接受 \$context。**21 测试**（OrderStateMachineTest 12 unit + OrderHistoryAutoLogTest 9 feature） |
| ⬜ | M08-PR32 后台订单 UI | M08 | 1.5 | | | |
| ✅ | M08-PR33 发货退款取消 | M08 | 1.5 | 2026-05-19 | `0b336f7` | Mall\\OrderController（admin 后台，与 Shop\\OrderController 客户前台分离）+ ship/refund/cancel 三个动作端点复用 OrderShipmentService / RefundService / OrderService；cancelOrder 接受 \$context 透传 reason/operator 到 OrderHistory。**25 测试**（Ship 7 + Refund 8 + Cancel 10）覆盖状态机/库存/审计/tenant 隔离 |
| ✅ | M09-PR34 customers | M09 | 1.0 | 2026-05-19 | `f2f858e` | 4 表（customers / customer_addresses / customer_groups / customer_group_translations）+ Customer 实现 AuthenticatableContract+OAuthenticatable（password 自动 hashed）+ CustomerGroup 多语言；Mall CRUD（customers 仅 list/show/update/destroy，不开放 store，email/phone/password 静默忽略）。**26 测试**（Customer 15 + CustomerGroup 11）+ 2 个 lang key |
| ✅ | M09-PR35 customer 注册登录 | M09 | 1.0 | 2026-05-22 | `7ee6a1f` | passport-customer guard + customers provider 隔离；VerificationCodeService（P0 日志 stub，10 分钟 TTL，租户隔离）+ Shop\AuthService（register / loginByPassword / loginByCode 首次自动建号 / issueToken / logout 真撤销 revoked=1）+ AuthController 6 端点（send-code / register / login / login-by-code 公开 throttle:5,1，me / logout 需 customer token）+ TenantMiddleware narrow 兼容 customer guard。**32 测试**（Email 13 + Phone 14 + RateLimit 5），9 个 lang key |
| ✅ | M09-PR36 customer 我的中心 API | M09 | 1.0 | 2026-05-22 | `cbe66f6` | CustomerAddressController CRUD 5 端点（`/me/addresses`）+ 默认地址语义（首条自动 default / 指定 default 降级其它 / 不允许 update 将自己降级为 0 / 删除 default 按 id 倒序晋升下一条）+ CustomerOrderController（`/me/orders`）身份强制来自 passport-customer guard（X-Customer-Id header 仿冒无效，游客订单 customer_id=null 不进，跨租户订单不可见）+ status 过滤 + 分页。**24 测试**（Address 15 + MyOrders 9）。**M09 模块 100% 收官** |
| ✅ | M10-PR37 mall 菜单权限 | M10 | 1.0 | 2026-05-22 | `4e391c6` | MallMenuSeeder 4 层菜单树（1 顶级 + 4 子目录 + 13 二级页面）+ MallPermissionSeeder 33 个 Type 3 按钮权限（`mall:resource:action`），全部 tenant_id=0 系统级菜单。DatabaseSeeder 在 RoleSeeder 之前注册以让 SUPER_ADMIN 默认获得。**15 测试**覆盖菜单树结构 / 权限命名规范 / SUPER_ADMIN 可见 / 仅授系统菜单的角色不可见（关键安全） / hasPermissionKey 精确匹配 + 超管短路 |
| ⬜ | M10-PR38 商品 UI 整合 | M10 | 1.0 | | | |
| ⬜ | M10-PR39 类目品牌 UI 整合 | M10 | 1.0 | | | |
| ⬜ | M10-PR40 订单 UI 整合 | M10 | 1.0 | | | |
| ⬜ | M10-PR41 客户 UI 整合 | M10 | 1.0 | | | |
| ✅ | M11-PR42 Nuxt 工程脚手架 | M11 | 1.0 | 2026-05-22 | `7de0acf` | 后端 ShopConfigController + GET `/api/v1/shop/config`（shop 中间件解析 host 子域 / X-Shop-Subdomain header）+ 6 测试。前端 `frontend-shop/`：Nuxt 3 SSR + i18n（zh-CN/en/ja/ko）+ tailwind + pinia + vueuse + @nuxt/eslint；middleware/tenant.global.ts（SSR/CSR 双端 host 子域推断 + 兑底环境变量 + fetch /shop/config）；composables/useApi.ts 统一 ApiClient（baseURL / X-Tenant-Id / X-Shop-Id / Bearer token / ApiError）； types/shop.ts 与后端响应严格对齐。CI 新增 frontend-shop job（format/lint/typecheck/build 4 步）。**后端 746 测试**，**前端 4 检查全过** |
| ✅ | M11-PR43 首页+类目页 | M11 | 1.5 | 2026-05-22 | `bc9ca36` | 后端 ShopCategoryController + ShopProductController（list/show）公开端点，tenant + shop + status=1 三重隔离。资源按 X-Locale header 命中翻译，未命中回退首条；pageSize 上限 60；越 shop 越租户 404。**16 测试**。前端 Header/Footer/CategoryNav/ProductCard 4 组件 + layouts/default.vue（useAsyncData 全站复用类目） + pages/index.vue（Hero + 最新 12 件网格 + useHead title/description/og） + pages/category/[id].vue（落地页 + prev/next 分页 + setResponseStatus(404)）。**后端 762 测试**，**前端 4 检查全过** |
| ✅ | M11-PR44 商品详情页 SEO | M11 | 1.5 | 2026-05-22 | `202a786` | 后端 ShopProductController::showBySlug + GET `/shop/products/by-slug/{slug}`（locale 优先命中 / 回退任意 locale / 跨租户与下架 404）**+7 测试**。前端 pages/product/[slug].vue SSR 完整 SEO 套件：Open Graph (og:type=product) + Twitter Card + canonical + hreflang 多语言（包含 x-default） + JSON-LD schema.org Product+Offer，404 时 setResponseStatus 不被收录。**3 组件**：ImageGallery（主图 eager + LCP 优化）/ SpecSelector、AddToCartButton 占位（PR45 接入）。**后端 769 测试**，**前端 4 检查全过** |
| ✅ | M11-PR45 购物车结账 | M11 | 1.5 | 2026-05-22 | `18286e3` | 后端 ShopProductResource 扩展变体 variants[]（sku/price/available + specification_values 按 X-Locale 命中），**+1 测试**。前端 Pinia cart store（fetch/add/update/remove/clear/preview/placeOrder + computed subtotal） + useShopSession（UUID cookie 游客身份） + useApi 自动注入 X-Session-Id / X-Locale。SpecSelector 按 specification_id 分组 chips（缺货变体 disabled + line-through，单变体自动选中） + AddToCartButton 调用 cart store。**5 页面**：cart 列表加减删 / checkout/index 地址 / checkout/payment 支付 + place-order / checkout/success noindex 展示订单号 / + 2 组件 AddressForm + PaymentSelector。**后端 770 测试**，**前端 4 检查全过** |
| ✅ | M11-PR46 我的中心 | M11 | 1.0 | 2026-05-22 | `1363419` | types/auth.ts + useAuthToken（cookie 30 天）+ useApi 自动注入 Authorization；Pinia auth store（login / register / sendCode / loginByCode / fetchMe / logout + isLoggedIn）；middleware/auth.ts 未登录 / token 过期 跳 /login?redirect 以便回跳。**6 页面**：login 双 mode（密码 / 验证码） + register / account/index profile 概览 + 3 入口 / account/orders 分页列表 + 详情（复用 /me/orders） / account/addresses 生命调 CRUD + 设默认（复用 CheckoutAddressForm）。Header 加登录态 + 退出。后端未动代码（复用 PR35/PR36 端点）。**前端 4 检查全过** |
| ✅ | M11-PR47 多语言/币种切换 | M11 | 0.5 | 2026-05-22 | `25cc162` | LocaleSwitcher（nuxt-i18n switchLocalePath） + CurrencySwitcher（5 币种 cookie 持久化 30 天） + useCurrency / formatPrice（Intl.NumberFormat） + useTheme 调色板映射（4 套预设） + plugins/theme.client.ts 启动时 watch shop.theme_id 同步注入 --color-primary / --color-secondary 到 documentElement。Header 嵌入两个切换器。币种 P0 仅持久化 UI（汇率换算待 M02 ExchangeRate 接入）。**前端 4 检查全过**。**M11 模块 100% 收官** |
| **总计** | | | **47** | | | |

### 阻塞与风险记录

> 遇到阻塞在此登记，标 ⏸️，写明原因 + 应对计划。

_暂无_

### 周复盘记录

> 每周一 15 分钟复盘，记录本周完成 / 下周计划 / 决策变更。

_暂无_

---

## 更新本文档

每完成一项，在对应条目下加：

```
**已完成**：YYYY-MM-DD，commit <hash>
```

不要直接删，留作迭代记录。
