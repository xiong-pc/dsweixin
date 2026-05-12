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
- 后端 `composer.json` 没 `pint`（Laravel 官方格式化）/ `larastan`

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

---

### TD-03 补 Unit 测试

**现状**：只有 1 个 `Tests\Unit\ExampleTest`，所有业务逻辑都靠 Feature test。

**痛点**：
- Feature test 慢（每个 5s 跑完 130 个，平均 38ms/test，但都要走 HTTP + DB）
- 纯逻辑（如 `AuthService::filterMenusByRole`、密码哈希、租户隔离规则）应该单元测试
- 重构 Service 时无 lockdown，回归靠 Feature test 间接覆盖

**落地步骤**：

1. 优先给以下纯逻辑层补 Unit test：
   - `App\Services\Api\AuthService::getRouteTree` 的菜单树过滤
   - `App\Services\Api\UserService` 的密码哈希、状态校验
   - 任何 `App\Models\*` 的访问器 / 修改器 / scope
2. 测试目标：`php artisan test --testsuite=Unit` 至少 30 个 case
3. 配置 `phpunit.xml` 让 Unit 测试用内存 SQLite 跑（无 DB 依赖更好）

**工作量**：2-3 天（按 Service 数量分批）
**风险**：低
**前置依赖**：无

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

**现状**：`phpunit.xml` 无 `<coverage>` 配置，跑测试不知道覆盖率。

**落地步骤**：

1. `backend/phpunit.xml` 加：

```xml
<coverage>
  <include>
    <directory>app</directory>
  </include>
  <exclude>
    <directory>app/Http/Middleware</directory>
  </exclude>
</coverage>
```

2. 安装 Xdebug 或 PCOV 跑覆盖率：

```bash
php -dxdebug.mode=coverage artisan test --coverage --min=70
```

3. CI 里也跑覆盖率，把报告上传到 Codecov / Coveralls

**工作量**：半天（含装 Xdebug + 把覆盖率提到 70%+）
**风险**：低
**前置依赖**：TD-01（CI 集成）

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

1. **TD-04 拆前端 bundle**（1h，缓存策略立刻见效）
2. **TD-04b element-plus 按需引入**（30min-1h，首屏体积真减）
3. **TD-01 加 CI/CD**（2h，护栏价值高）
4. **TD-02 加 lint/format**（半天，配合 CI）
5. **TD-05 补测试覆盖率**（半天，配合 CI）
6. **TD-03 补 Unit 测试**（持续投入，可在补业务时同步加）
7. **TD-06 Service interface**（按需）
8. **TD-07 commit 规范**（1h，团队 >2 人时再做）
9. **TD-08 OpenAPI**（开始多人协作或多端调用时再做）
10. **TD-09 监控**（上生产前必做）

## 更新本文档

每完成一项，在对应条目下加：

```
**已完成**：YYYY-MM-DD，commit <hash>
```

不要直接删，留作迭代记录。
