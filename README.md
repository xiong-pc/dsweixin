# dsweixin

全栈管理后台系统：**Laravel 13 REST API + Vue 3 SPA**，支持多租户、RBAC 权限、商城系统。

## 项目结构

| 目录 | 说明 |
|------|------|
| [`backend/`](backend/README.md) | Laravel 13 REST API（PHP 8.3 + Passport OAuth + spatie/laravel-permission） |
| [`frontend/`](frontend/README.md) | Vue 3 + TypeScript SPA（Vite 7 + Element Plus + Pinia） |
| [`docs/`](docs/) | 项目文档：API、数据库、需求、设计、实现计划 |

## 快速启动

```bash
# 后端
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve            # 默认 http://127.0.0.1:8000

# 前端（另开终端）
cd frontend
npm install
npm run dev                  # 默认 http://127.0.0.1:5173
```

## 技术栈

**后端**：PHP 8.3、Laravel 13、Laravel Passport（OAuth2 JWT）、spatie/laravel-permission（RBAC）、SQLite/MySQL

**前端**：Vue 3、TypeScript、Vite 7、Element Plus、Pinia、Vue Router 4、Vue i18n、axios

## 文档导航

- 接口文档：[`docs/api.md`](docs/api.md)
- 数据库设计：[`docs/database.md`](docs/database.md)
- 商城系统需求文档：[`docs/superpowers/specs/2026-04-24-mall-system-requirements.md`](docs/superpowers/specs/2026-04-24-mall-system-requirements.md)
- 商城系统设计文档：[`docs/superpowers/specs/2026-04-24-mall-system-design.md`](docs/superpowers/specs/2026-04-24-mall-system-design.md)
- 商城系统实现计划：[`docs/superpowers/plans/2026-04-24-mall-system.md`](docs/superpowers/plans/2026-04-24-mall-system.md)
- 速查表：[`docs/cheatsheets/`](docs/cheatsheets/)

## AI 协作

本项目使用 [superpowers-zh](https://www.npmjs.com/package/superpowers-zh) 工作流。AI 协作约定见各级 `CLAUDE.md`：

- 根目录 [`CLAUDE.md`](CLAUDE.md)：项目整体约定 + 模块映射
- [`backend/CLAUDE.md`](backend/CLAUDE.md)：后端开发约定
- [`frontend/CLAUDE.md`](frontend/CLAUDE.md)：前端开发约定

设计文档（spec）位于 `docs/superpowers/specs/`，实现计划位于 `docs/superpowers/plans/`。

## 核心特性

- **多租户**：所有业务接口通过 `tenant` 中间件自动隔离数据
- **RBAC 权限**：角色绑定菜单权限，前端路由由后端动态生成
- **接口版本化**：所有 API 挂在 `/api/v1/` 前缀下
