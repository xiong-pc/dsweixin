# Backend — Laravel 13

## 技术栈

- PHP 8.3 + Laravel 13
- Laravel Passport（OAuth2 JWT 认证）
- spatie/laravel-permission（RBAC 角色权限）
- SQLite（开发）/ MySQL（生产）

## 常用命令

```bash
php artisan serve              # 启动开发服务器
php artisan migrate            # 执行迁移
php artisan migrate:fresh --seed  # 重置数据库并填充
php artisan tinker             # 交互式 REPL
./vendor/bin/pint              # 代码格式化
php artisan test               # 运行测试
```

## API 约定

- 所有接口前缀：`/api/v1/`
- 响应格式：`{ code, msg, data }`（参考现有 Controller 实现）
- 认证：Bearer Token（Passport 签发）
- 中间件栈：`auth:api` → `tenant`（业务接口必须经过两层）

## 目录约定

```
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── CodeGenerator.php   # Artisan 代码生成器，快速生成 CRUD 骨架
│   ├── Enums/                      # PHP 枚举类，定义状态码、类型等常量
│   ├── Exceptions/                 # 自定义异常处理
│   ├── Http/
│   │   ├── Controllers/Api/        # 所有 API 控制器，按模块一文件一类；禁止直接调用 Model
│   │   │   ├── Controller.php      # Api 基类，封装 success() / error() / paginate() 响应方法
│   │   │   ├── AuthController.php  # 登录、登出、刷新 Token、获取当前用户及权限菜单
│   │   │   ├── UserController.php  # 用户 CRUD、状态切换、重置密码
│   │   │   ├── RoleController.php  # 角色 CRUD、角色绑定菜单
│   │   │   ├── MenuController.php  # 菜单 CRUD（树形结构）
│   │   │   ├── DeptController.php  # 部门 CRUD（树形结构）
│   │   │   ├── DictController.php  # 字典 CRUD 及字典项列表
│   │   │   ├── ConfigController.php  # 系统配置 CRUD
│   │   │   ├── NoticeController.php  # 公告 CRUD、发布、撤回
│   │   │   └── TenantController.php  # 租户 CRUD（仅超级管理员可访问）
│   │   ├── Middleware/
│   │   │   └── TenantMiddleware.php  # 校验租户状态与有效期，禁用或过期则拒绝访问
│   │   ├── Requests/Api/           # FormRequest 表单验证，目录结构镜像 Controllers/Api/
│   │   │   ├── Auth/LoginRequest.php
│   │   │   ├── User/StoreUserRequest.php / UpdateUserRequest.php / UpdateStatusRequest.php
│   │   │   ├── Role/StoreRoleRequest.php / UpdateRoleRequest.php
│   │   │   ├── Menu/StoreMenuRequest.php / UpdateMenuRequest.php
│   │   │   ├── Dept/StoreDeptRequest.php / UpdateDeptRequest.php
│   │   │   ├── Dict/StoreDictRequest.php / UpdateDictRequest.php
│   │   │   ├── DictItem/StoreDictItemRequest.php / UpdateDictItemRequest.php
│   │   │   ├── Config/StoreConfigRequest.php / UpdateConfigRequest.php
│   │   │   ├── Notice/StoreNoticeRequest.php / UpdateNoticeRequest.php
│   │   │   └── Tenant/StoreTenantRequest.php / UpdateTenantRequest.php
│   │   └── Resources/              # API Resource 响应转换，统一输出字段格式（待扩展）
│   ├── Models/
│   │   ├── Traits/
│   │   │   └── BelongsToTenant.php # 多租户 Trait，自动注入 tenant_id 查询条件
│   │   ├── User.php                # 用户模型，关联角色、部门、租户
│   │   ├── Role.php                # 角色模型，关联权限和菜单
│   │   ├── Menu.php                # 菜单模型，树形结构，关联角色权限
│   │   ├── Dept.php                # 部门模型，树形结构
│   │   ├── Dict.php / DictItem.php # 字典及字典项模型
│   │   ├── SysConfig.php           # 系统配置模型
│   │   ├── Notice.php              # 公告模型，含发布状态流转
│   │   └── Tenant.php              # 租户模型，含状态和过期时间
│   ├── Services/Api/               # 业务逻辑层，目录结构镜像 Controllers/Api/
│   │   ├── AuthService.php
│   │   ├── UserService.php
│   │   ├── RoleService.php
│   │   ├── MenuService.php
│   │   ├── DeptService.php
│   │   ├── DictService.php / DictItemService.php
│   │   ├── ConfigService.php
│   │   ├── NoticeService.php
│   │   └── TenantService.php
│   └── Scopes/
│       └── TenantScope.php         # 全局查询 Scope，自动按 tenant_id 过滤数据
├── database/
│   ├── migrations/                 # 数据库迁移文件，按时间戳排序
│   └── seeders/                    # 初始数据填充（角色、菜单、超管账号等）
├── routes/
│   └── api.php                     # 所有 API 路由定义，按中间件分组
└── composer.json                   # PHP 依赖管理
```

## 关键模式

**多租户隔离**：用户携带 `tenant_id`，`TenantMiddleware` 校验租户状态和有效期。新增业务模型时若需要租户隔离，在 Model 中添加 `tenant_id` 字段并使用全局 Scope。

**权限控制**：使用 spatie/laravel-permission，权限粒度到菜单，角色绑定菜单列表，`auth/routes` 接口返回当前用户有权限的菜单树。

**分层约定（严格执行）**：`Controllers/Api/` 下的 Controller 禁止直接引用任何 Model 类。所有数据库操作必须通过对应的 Service 完成，Controller 只负责：接收请求、调用 Service、返回响应。Service 与 Controller 一一对应（`UserController` → `UserService`），Model 只在 Service 层中使用。

```
// 正确
class UserController {
    public function index(Request $request, UserService $service): JsonResponse {
        return response()->json($service->list($request->all()));
    }
}

// 禁止 — Controller 直接操作 Model
class UserController {
    public function index(): JsonResponse {
        return response()->json(User::paginate()); // ❌
    }
}
```

**Controller 风格**：使用 `apiResource` 注册标准 CRUD，返回 `JsonResponse`，过滤、分页、业务逻辑全部下沉到 Service。验证逻辑全部用 FormRequest，Controller 方法签名直接注入对应 Request 类，不写内联 `$request->validate()`。

**目录镜像约定**：`Services/Api/`、`Requests/Api/` 的子目录结构必须与 `Controllers/Api/` 保持一致。新增业务模块时三处同步创建，命名规则：
- Controller：`{Module}Controller`
- Service：`Services/Api/{Module}Service`
- Request：`Requests/Api/{Module}/Store{Module}Request` / `Update{Module}Request`

**响应封装与多语言**：所有 Api Controller 继承 `App\Http\Controllers\Api\Controller`，通过基类方法返回响应，消息内容统一走语言包。

```php
// 成功
return $this->success($data);
return $this->success($data, 'api.created');

// 失败
return $this->error('api.not_found', 404);

// 分页
return $this->paginate(User::paginate(15));
```

语言包位于 `lang/{locale}/api.php`，切换语言只需在请求头传 `Accept-Language` 或在 `.env` 设置 `APP_LOCALE`，消息自动跟随切换。禁止在 Controller / Middleware 中硬编码中文消息字符串。

## 注释规范

**每个新 PHP 文件顶部必须加文件头注释**（紧跟在 `<?php` 之后、`namespace` 之前）：

```php
<?php

/**
 * @Author: xiong-pc
 * @Email: 562740366@qq.com
 * @Date: 2026-04-10 12:00:00
 * @Version: 1.0.0
 */

namespace App\Http\Controllers\Api;
```

**每个新方法必须加方法注释**（放在方法声明上方）：

```php
/**
 * @Author: xiong-pc
 * @Date: 2026-04-10 12:00:00
 * @Description: 获取用户列表（分页）
 * @param Request $request 请求对象
 * @return JsonResponse
 */
public function index(Request $request): JsonResponse
```

规则：
- `@Date` 使用实际创建日期时间，格式 `Y-m-d H:i:s`
- `@Description` 用中文简要描述方法用途
- 每个参数单独写一行 `@param 类型 $名称 描述`
- 返回类型为 `void` 时省略 `@return`
- 已有文件中新增方法同样需要方法注释
- 修改已有方法时不需要更新其注释（避免注释漂移）

## 数据库迁移约定

**新建字段默认值规则（严格执行）**：

| 字段类型 | 是否可空 | 默认值 | 示例 |
|----------|----------|--------|------|
| `tinyint` / `int` / `bigint` / `unsignedBigInteger` 等整型 | NOT NULL | `0` | `$table->tinyInteger('status')->default(0)` |
| `varchar` / `char` / `string` 等字符串型 | NOT NULL | `''` | `$table->string('name')->default('')` |
| 整型业务外键（如 `dept_id`、`parent_id`） | NOT NULL | `0` | `$table->unsignedBigInteger('dept_id')->default(0)` |
| 字符串型业务外键 | NOT NULL | `''` | `$table->string('some_fk')->default('')` |
| `text` / `longText` 大文本 | 允许 NULL | — | `$table->text('content')->nullable()` |
| `timestamp` / `datetime` 时间型 | 允许 NULL | — | `$table->timestamp('published_at')->nullable()` |

> 外键字段类型跟随主键类型，默认值规则与同类型普通字段保持一致。用 `0` 或 `''` 表示"未关联"，业务层在读取时自行判断。

```php
// 正确
$table->tinyInteger('status')->default(0);
$table->string('nickname')->default('');
$table->unsignedBigInteger('dept_id')->default(0);   // 整型外键
$table->text('remark')->nullable();
$table->timestamp('expired_at')->nullable();

// 禁止
$table->tinyInteger('status');                        // ❌ 无默认值
$table->string('nickname');                           // ❌ 无默认值
$table->unsignedBigInteger('dept_id')->nullable();    // ❌ 外键不应为 nullable
```
