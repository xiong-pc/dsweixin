# dsweixin

全栈管理后台系统，采用前后端分离架构。

## 项目结构

```
dsweixin/
├── backend/    # Laravel 13 REST API
└── frontend/   # Vue 3 + TypeScript SPA
```

## 架构关系

- 后端提供 `/api/v1/` 前缀的 RESTful API，使用 Laravel Passport 做 OAuth 认证
- 前端通过 axios 调用后端 API，JWT token 存储在 localStorage
- 多租户设计：所有业务接口通过 `tenant` 中间件隔离数据
- RBAC 权限：基于 spatie/laravel-permission，角色绑定菜单权限

## 开发环境启动

```bash
# 后端（backend/）
php artisan serve

# 前端（frontend/）
npm run dev
```

## 核心业务模块

| 模块 | 后端 Controller | 前端路由 |
|------|----------------|---------|
| 认证 | AuthController | /login |
| 用户管理 | UserController | /system/user |
| 角色权限 | RoleController | /system/role |
| 菜单管理 | MenuController | /system/menu |
| 部门管理 | DeptController | /system/dept |
| 字典管理 | DictController | /system/dict |
| 系统配置 | ConfigController | /system/config |
| 公告管理 | NoticeController | /system/notice |
| 租户管理 | TenantController | /system/tenant |

## Compact instructions

压缩时保留：代码变更、接口设计决策、未解决的 bug、架构约定。忽略：调试过程、已解决的错误信息、重复的问答。
