# 数据库设计文档

> **数据库**：MySQL 8.0+
> **表前缀**：`ds_`（生产环境 `.env` 中 `DB_PREFIX=ds_`）
> **字符集**：utf8mb4 / utf8mb4_unicode_ci
> **时间字段**：统一使用 `created_at` / `updated_at`（Laravel `timestamps()`），软删除使用 `deleted_at`（`softDeletes()`）
> **字段默认值约定**（新建字段必须遵守）：
> - 整型（`tinyint` / `int` / `bigint` 等）：`NOT NULL DEFAULT 0`
> - 字符串型（`varchar` / `char`）：`NOT NULL DEFAULT ''`
> - 文本型（`text` / `longtext`）：允许 `NULL`，不设默认值
> - 时间型（`timestamp` / `datetime`）：允许 `NULL`，不设默认值
> - 业务上可为空的外键（如 `dept_id`）：允许 `NULL`

---

## 目录

### 业务表

| 表名 | 说明 |
|------|------|
| [tenants](#tenants-租户表) | SaaS 多租户 |
| [users](#users-用户表) | 系统登录用户 |
| [roles](#roles-角色表) | RBAC 角色 |
| [menus](#menus-菜单权限表) | 路由菜单 / 按钮权限树 |
| [depts](#depts-部门表) | 组织架构部门 |
| [dicts](#dicts-数据字典表) | 字典分类 |
| [dict_items](#dict_items-字典项表) | 字典选项值 |
| [configs](#configs-系统配置表) | 键值对系统配置 |
| [notices](#notices-公告通知表) | 系统公告 / 通知 |

### 中间关联表

| 表名 | 说明 |
|------|------|
| [role_menus](#role_menus-角色菜单关联表) | 角色 ↔ 菜单（多对多） |
| [user_roles](#user_roles-用户角色关联表) | 用户 ↔ 角色（多对多） |
| [role_depts](#role_depts-角色部门关联表) | 角色 ↔ 部门（数据权限，多对多） |

### Passport OAuth 表

| 表名 | 说明 |
|------|------|
| [oauth_clients](#oauth_clients-oauth-客户端表) | OAuth 应用客户端 |
| [oauth_access_tokens](#oauth_access_tokens-访问令牌表) | Access Token |
| [oauth_refresh_tokens](#oauth_refresh_tokens-刷新令牌表) | Refresh Token |
| [oauth_auth_codes](#oauth_auth_codes-授权码表) | Authorization Code |
| [oauth_device_codes](#oauth_device_codes-设备码表) | Device Code |

### Laravel 系统表

| 表名 | 说明 |
|------|------|
| [sessions](#sessions-会话表) | 数据库 Session 存储 |
| [password_reset_tokens](#password_reset_tokens-密码重置表) | 密码重置 Token |
| [cache / cache_locks](#cache--cache_locks-缓存表) | 数据库缓存 |
| [jobs / job_batches / failed_jobs](#jobs--job_batches--failed_jobs-队列表) | 队列任务 |

---

## 业务表详情

---

### tenants 租户表

多租户架构的顶层隔离单元。每个租户下的数据（用户、角色、菜单、配置等）严格隔离。
`tenant_id = 0` 为系统级（超级管理员），不属于任何租户。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| name | varchar(50) | NOT NULL | 无默认值 | 租户名称，如"默认租户" |
| code | varchar(30) | NOT NULL | 无默认值 | 租户编码，全局唯一，如 `DEFAULT` |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| contact_name | varchar(50) | NULL | NULL | 联系人姓名 |
| contact_phone | varchar(20) | NULL | NULL | 联系电话 |
| expired_at | timestamp | NULL | NULL | 到期时间，`NULL` 表示永不过期 |
| remark | varchar(255) | NULL | NULL | 备注说明 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |
| deleted_at | timestamp | NULL | NULL | 软删除时间，非 NULL 表示已删除 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| tenants_code_unique | UNIQUE | code |

---

### users 用户表

系统所有可登录用户，通过 `tenant_id` 归属租户，`tenant_id = 0` 为超级管理员（不隔离租户数据）。
密码字段由 Laravel `hashed` Cast 自动 bcrypt 哈希，禁止明文存储。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NOT NULL | `0` | 所属租户 ID，`0`=系统级超管 |
| dept_id | bigint unsigned | NULL | NULL | 所属部门 ID，NULL=未分配部门 |
| username | varchar(64) | NOT NULL | 无默认值 | 登录用户名，全局唯一 |
| nickname | varchar(64) | NOT NULL | `''` | 显示昵称 |
| name | varchar(255) | NOT NULL | 无默认值 | 姓名（由后端自动同步 nickname） |
| email | varchar(255) | NOT NULL | 无默认值 | 邮箱，全局唯一 |
| email_verified_at | timestamp | NULL | NULL | 邮箱验证时间，NULL=未验证 |
| phone | varchar(20) | NULL | NULL | 手机号码 |
| password | varchar(255) | NOT NULL | 无默认值 | 密码（bcrypt 哈希值） |
| avatar | varchar(255) | NOT NULL | `''` | 头像 URL，空字符串=使用默认头像 |
| gender | tinyint(1) | NOT NULL | `0` | 性别：`0`=未知，`1`=男，`2`=女 |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| remember_token | varchar(100) | NULL | NULL | "记住我" Token（Web Session 用） |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| users_email_unique | UNIQUE | email |
| users_username_unique | UNIQUE | username |
| users_tenant_id_index | INDEX | tenant_id |
| users_dept_id_index | INDEX | dept_id |

---

### roles 角色表

RBAC 权限角色。角色通过 `role_menus` 绑定菜单权限，通过 `user_roles` 绑定用户。
内置角色编码：`SUPER_ADMIN`（超级管理员）、`ADMIN`（租户管理员）、`USER`（普通用户）。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NOT NULL | `0` | 所属租户 ID，`0`=系统级角色 |
| name | varchar(64) | NOT NULL | 无默认值 | 角色显示名称，如"系统管理员" |
| code | varchar(64) | NOT NULL | 无默认值 | 角色编码，全局唯一，全大写，如 `ADMIN` |
| data_scope | tinyint(1) | NOT NULL | `0` | 数据权限范围：`0`=全部，`1`=本部门，`2`=本部门及子部门，`3`=仅本人，`4`=自定义（配合 role_depts） |
| sort | int | NOT NULL | `0` | 排序权重，值越小越靠前 |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| remark | varchar(255) | NOT NULL | `''` | 备注说明 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| roles_code_unique | UNIQUE | code |
| roles_tenant_id_index | INDEX | tenant_id |

---

### menus 菜单权限表

路由权限树，同时承担前端动态路由（type=1/2/4）和按钮级权限（type=3）两个职责。
通过 `parent_id` 自关联形成多级树形结构，`parent_id = 0` 为根节点。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NULL | NULL | 租户 ID：`NULL`=系统公共菜单，非 NULL=租户自定义菜单 |
| parent_id | bigint unsigned | NOT NULL | `0` | 父菜单 ID，`0`=根节点（顶级目录） |
| name | varchar(64) | NOT NULL | 无默认值 | 菜单 / 按钮名称，如"用户管理" |
| type | tinyint(1) | NOT NULL | 无默认值 | 类型：`1`=目录，`2`=菜单，`3`=按钮，`4`=外链 |
| path | varchar(128) | NOT NULL | `''` | 路由路径（Vue Router path），如 `/system` 或 `user` |
| component | varchar(128) | NOT NULL | `''` | 前端组件路径，如 `system/user/index`；目录固定为 `Layout` |
| permission | varchar(128) | NOT NULL | `''` | 权限标识，仅 type=3 有效，如 `sys:user:add` |
| icon | varchar(64) | NOT NULL | `''` | Element Plus 图标名，如 `User` |
| sort | int | NOT NULL | `0` | 排序权重，值越小越靠前 |
| visible | tinyint(1) | NOT NULL | `1` | 是否在侧边栏显示：`1`=显示，`0`=隐藏 |
| redirect | varchar(128) | NOT NULL | `''` | 重定向路径，目录类型使用，如 `/system/user` |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| menus_tenant_id_index | INDEX | tenant_id |

**菜单类型说明**

| type | 名称 | path | component | permission | 说明 |
|------|------|------|-----------|-----------|------|
| 1 | 目录 | 以 `/` 开头 | `Layout` | 空 | 侧边栏折叠组，自身不渲染页面 |
| 2 | 菜单 | 相对路径 | `views/` 下的组件路径 | 空 | 实际页面路由 |
| 3 | 按钮 | 空 | 空 | 如 `sys:user:add` | 页面内按钮权限，不参与路由 |
| 4 | 外链 | 完整 URL | 空 | 空 | 在侧边栏显示，点击新窗口打开 |

---

### depts 部门表

组织架构部门，树形结构，通过 `parent_id` 自关联，`parent_id = 0` 为顶层部门。
每个部门归属一个租户（`tenant_id`），全局 Scope 自动隔离。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NOT NULL | `0` | 所属租户 ID |
| parent_id | bigint unsigned | NOT NULL | `0` | 父部门 ID，`0`=顶层部门 |
| name | varchar(64) | NOT NULL | 无默认值 | 部门名称，如"技术部" |
| sort | int | NOT NULL | `0` | 排序权重，值越小越靠前 |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| depts_tenant_id_index | INDEX | tenant_id |

---

### dicts 数据字典表

字典分类主表，每个字典对应一类选项集合（如性别、状态）。
`tenant_id = NULL` 为系统预置公共字典（全租户共享），非 NULL 为租户自定义字典。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NULL | NULL | 租户 ID：`NULL`=系统公共字典，非 NULL=租户字典 |
| name | varchar(64) | NOT NULL | 无默认值 | 字典名称，如"用户性别" |
| code | varchar(64) | NOT NULL | 无默认值 | 字典编码，全局唯一，如 `sys_user_gender` |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| remark | varchar(255) | NOT NULL | `''` | 备注说明 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| dicts_code_unique | UNIQUE | code |
| dicts_tenant_id_index | INDEX | tenant_id |

---

### dict_items 字典项表

字典选项值，从属于 `dicts`。每条记录是一个可选项，前端下拉框的数据来源。
删除父字典时级联删除所有字典项（由 `DictService::delete` 处理）。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NULL | NULL | 租户 ID（冗余自父字典） |
| dict_id | bigint unsigned | NOT NULL | 无默认值 | 所属字典 ID（关联 dicts.id） |
| label | varchar(64) | NOT NULL | 无默认值 | 显示标签，如"男" |
| value | varchar(64) | NOT NULL | 无默认值 | 存储值，如 `1`（字符串形式） |
| sort | int | NOT NULL | `0` | 排序权重，值越小越靠前 |
| status | tinyint(1) | NOT NULL | `1` | 状态：`1`=正常，`0`=禁用 |
| remark | varchar(255) | NOT NULL | `''` | 备注说明 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| dict_items_dict_id_index | INDEX | dict_id |
| dict_items_tenant_id_index | INDEX | tenant_id |

---

### configs 系统配置表

键值对形式的运行时配置，按租户隔离。`(tenant_id, key)` 联合唯一，同一租户内键名不可重复。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NOT NULL | `0` | 所属租户 ID，`0`=系统全局配置 |
| name | varchar(64) | NOT NULL | 无默认值 | 配置显示名称，如"网站标题" |
| key | varchar(128) | NOT NULL | 无默认值 | 配置键名，租户内唯一，如 `site_title` |
| value | text | NULL | NULL | 配置值（存储字符串，由 `type` 决定解析方式） |
| type | tinyint(1) | NOT NULL | `0` | 值类型：`0`=字符串，`1`=数字，`2`=布尔，`3`=JSON |
| remark | varchar(255) | NOT NULL | `''` | 备注说明 |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| configs_tenant_id_key_unique | UNIQUE | (tenant_id, key) |
| configs_tenant_id_index | INDEX | tenant_id |

---

### notices 公告通知表

系统公告和通知，支持草稿 → 发布 → 撤回三段式状态流转。
`publisher_id` 在发布时由后端自动写入当前用户 ID。

| 字段 | 数据类型 | 可空 | 默认值 | 说明 |
|------|---------|------|--------|------|
| id | bigint unsigned | NOT NULL | AUTO_INCREMENT | 主键 |
| tenant_id | bigint unsigned | NOT NULL | `0` | 所属租户 ID |
| title | varchar(128) | NOT NULL | 无默认值 | 标题 |
| type | tinyint(1) | NOT NULL | `1` | 类型：`1`=通知，`2`=公告 |
| level | tinyint(1) | NOT NULL | `0` | 级别：`0`=普通，`1`=重要，`2`=紧急 |
| content | text | NULL | NULL | 正文内容（支持 HTML 富文本） |
| publisher_id | bigint unsigned | NULL | NULL | 发布人 ID（关联 users.id） |
| status | tinyint(1) | NOT NULL | `0` | 状态：`0`=草稿，`1`=已发布，`2`=已撤回 |
| publish_time | timestamp | NULL | NULL | 发布时间，草稿时为 NULL |
| created_at | timestamp | NULL | NULL | 创建时间 |
| updated_at | timestamp | NULL | NULL | 最后修改时间 |

**状态流转**

```
草稿(0) ──publish──▶ 已发布(1) ──revoke──▶ 已撤回(2)
```

**索引**

| 索引名 | 类型 | 字段 |
|--------|------|------|
| PRIMARY | 主键 | id |
| notices_tenant_id_index | INDEX | tenant_id |

---

## 中间关联表详情

---

### role_menus 角色菜单关联表

角色与菜单的多对多关联。决定角色可访问哪些路由页面和按钮权限。
由 `RoleService::updateMenus` 全量 `sync` 管理，不单独增删。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| role_id | bigint unsigned | NOT NULL | 角色 ID（联合主键） |
| menu_id | bigint unsigned | NOT NULL | 菜单 ID（联合主键） |

**索引**：`PRIMARY (role_id, menu_id)`

---

### user_roles 用户角色关联表

用户与角色的多对多关联。一个用户可绑定多个角色，权限取并集。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| user_id | bigint unsigned | NOT NULL | 用户 ID（联合主键） |
| role_id | bigint unsigned | NOT NULL | 角色 ID（联合主键） |

**索引**：`PRIMARY (user_id, role_id)`

---

### role_depts 角色部门关联表

用于数据权限 `data_scope = 4`（自定义）的场景，显式指定该角色可访问哪些部门的数据。
`data_scope ≠ 4` 时此表数据无效。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| role_id | bigint unsigned | NOT NULL | 角色 ID（联合主键） |
| dept_id | bigint unsigned | NOT NULL | 部门 ID（联合主键） |

**索引**：`PRIMARY (role_id, dept_id)`

---

## Passport OAuth 表详情

---

### oauth_clients OAuth 客户端表

记录所有注册的 OAuth 应用客户端。系统通过 `php artisan passport:install` 自动创建 Personal Access Client。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | uuid | NOT NULL | 客户端 UUID（主键） |
| owner_id | bigint | NULL | 客户端所有者 ID |
| owner_type | varchar | NULL | 多态类型 |
| name | varchar | NOT NULL | 客户端名称 |
| secret | varchar | NULL | 客户端密钥（Personal Access 类型为 NULL） |
| provider | varchar | NULL | 用户提供者，默认 `users` |
| redirect_uris | text | NOT NULL | 允许的回调地址列表（JSON） |
| grant_types | text | NOT NULL | 支持的授权类型（JSON），如 `["personal_access"]` |
| revoked | tinyint(1) | NOT NULL | 是否已吊销：`1`=已吊销，`0`=正常 |
| created_at | timestamp | NULL | 创建时间 |
| updated_at | timestamp | NULL | 最后修改时间 |

---

### oauth_access_tokens 访问令牌表

存储所有已颁发的 Access Token 记录。Token 内容以 JWT 形式下发，此表用于吊销校验。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | char(80) | NOT NULL | Token ID（主键，JWT jti 字段值） |
| user_id | bigint | NULL | 所属用户 ID（关联 users.id） |
| client_id | uuid | NOT NULL | 所属 OAuth 客户端 ID |
| name | varchar | NULL | Token 名称，Personal Access 固定为 `Personal Access Token` |
| scopes | text | NULL | 授权范围（JSON 数组），Personal Access 默认为 `[]` |
| revoked | tinyint(1) | NOT NULL | 是否已吊销：`1`=已吊销（登出后置 1） |
| expires_at | datetime | NULL | Token 过期时间（由 `passport.token_expire_days` 控制） |
| created_at | timestamp | NULL | 创建时间 |
| updated_at | timestamp | NULL | 最后修改时间 |

**索引**：`user_id`（INDEX）

---

### oauth_refresh_tokens 刷新令牌表

与 Access Token 一对一关联，用于无感刷新。调用 `/auth/refresh` 时旧 Token 和 Refresh Token 同时吊销（Token 轮换）。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | char(80) | NOT NULL | Refresh Token ID（主键） |
| access_token_id | char(80) | NOT NULL | 关联的 Access Token ID |
| revoked | tinyint(1) | NOT NULL | 是否已吊销 |
| expires_at | datetime | NULL | 过期时间 |

**索引**：`access_token_id`（INDEX）

---

### oauth_auth_codes 授权码表

Authorization Code Flow 使用的临时授权码，本系统使用 Personal Access Token，此表通常为空。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | char(80) | NOT NULL | 授权码 ID（主键） |
| user_id | bigint | NOT NULL | 用户 ID |
| client_id | uuid | NOT NULL | 客户端 ID |
| scopes | text | NULL | 授权范围 |
| revoked | tinyint(1) | NOT NULL | 是否已使用 / 吊销 |
| expires_at | datetime | NULL | 过期时间 |

---

### oauth_device_codes 设备码表

Device Authorization Flow 使用的设备码，本系统当前未使用此授权流程，此表通常为空。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | char(80) | NOT NULL | 设备码 ID（主键） |
| user_id | bigint | NULL | 用户 ID（授权后填入） |
| client_id | uuid | NOT NULL | 客户端 ID |
| user_code | char(8) | NOT NULL | 用户侧输入的短码（UNIQUE） |
| scopes | text | NOT NULL | 授权范围 |
| revoked | tinyint(1) | NOT NULL | 是否已吊销 |
| user_approved_at | datetime | NULL | 用户授权时间 |
| last_polled_at | datetime | NULL | 设备最后轮询时间 |
| expires_at | datetime | NULL | 过期时间 |

---

## Laravel 系统表详情

---

### sessions 会话表

数据库驱动的 Session 存储（`.env` 中 `SESSION_DRIVER=database`）。API 项目通常不依赖此表，Web 页面登录时使用。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| id | varchar | NOT NULL | Session ID（主键） |
| user_id | bigint | NULL | 关联用户 ID（INDEX） |
| ip_address | varchar(45) | NULL | 客户端 IP |
| user_agent | text | NULL | 客户端 UA |
| payload | longtext | NOT NULL | Session 数据（序列化） |
| last_activity | int | NOT NULL | 最后活跃时间戳（INDEX） |

---

### password_reset_tokens 密码重置表

存储密码重置邮件中的临时 Token，使用后立即删除。本系统通过管理后台直接重置密码（`/users/{id}/reset-password`），此表通常为空。

| 字段 | 数据类型 | 可空 | 说明 |
|------|---------|------|------|
| email | varchar | NOT NULL | 用户邮箱（主键） |
| token | varchar | NOT NULL | 重置 Token（哈希值） |
| created_at | timestamp | NULL | 创建时间 |

---

### cache / cache_locks 缓存表

数据库缓存驱动（`.env` 中 `CACHE_STORE=database`）。

**cache**

| 字段 | 数据类型 | 说明 |
|------|---------|------|
| key | varchar | 缓存键（主键） |
| value | mediumtext | 缓存值（序列化） |
| expiration | bigint | 过期时间戳（INDEX） |

**cache_locks**

| 字段 | 数据类型 | 说明 |
|------|---------|------|
| key | varchar | 锁键（主键） |
| owner | varchar | 锁持有者标识 |
| expiration | bigint | 过期时间戳（INDEX） |

---

### jobs / job_batches / failed_jobs 队列表

数据库队列驱动（`.env` 中 `QUEUE_CONNECTION=database`）。

**jobs**（待执行任务）

| 字段 | 数据类型 | 说明 |
|------|---------|------|
| id | bigint | 主键 |
| queue | varchar | 队列名称（INDEX） |
| payload | longtext | 任务数据（序列化） |
| attempts | tinyint unsigned | 已尝试次数 |
| reserved_at | int unsigned | 被取出时间戳（NULL=未取出） |
| available_at | int unsigned | 可执行时间戳 |
| created_at | int unsigned | 创建时间戳 |

**job_batches**（批量任务）

| 字段 | 数据类型 | 说明 |
|------|---------|------|
| id | varchar | 批次 ID（主键） |
| name | varchar | 批次名称 |
| total_jobs | int | 总任务数 |
| pending_jobs | int | 待执行数 |
| failed_jobs | int | 失败数 |
| failed_job_ids | longtext | 失败任务 ID 列表 |
| options | mediumtext | 配置选项 |
| cancelled_at | int | 取消时间戳 |
| created_at | int | 创建时间戳 |
| finished_at | int | 完成时间戳 |

**failed_jobs**（失败任务归档）

| 字段 | 数据类型 | 说明 |
|------|---------|------|
| id | bigint | 主键 |
| uuid | varchar | 唯一标识（UNIQUE） |
| connection | text | 连接驱动 |
| queue | text | 队列名称 |
| payload | longtext | 任务数据 |
| exception | longtext | 异常信息 |
| failed_at | timestamp | 失败时间（DEFAULT CURRENT_TIMESTAMP） |

---

## 多租户隔离说明

| 表 | tenant_id 语义 | TenantScope 行为 |
|---|---|---|
| users | `0`=超管，`>0`=租户用户 | 非超管用户只能查看同租户用户 |
| roles | `0`=系统级角色，`>0`=租户角色 | 非超管用户只能查看同租户角色 |
| depts | `>0`=租户部门（0 不使用） | 非超管用户只能查看同租户部门 |
| menus | `NULL`=公共菜单，`>0`=租户菜单 | 使用 `includeNullTenantInScope=true` 同时查看公共和本租户菜单 |
| dicts | `NULL`=公共字典，`>0`=租户字典 | 同菜单，兼容公共和租户字典 |
| configs | `0`=全局配置，`>0`=租户配置 | 使用 `includeZeroTenantInScope=true` 同时查看全局和本租户配置 |
| notices | `>0`=租户公告 | 非超管用户只能查看同租户公告 |

**`TenantMiddleware` 校验逻辑**（每次认证请求都执行）：
1. `tenant_id = 0` → 系统级超管，跳过校验
2. `tenant.status ≠ 1` → 返回 403（租户已禁用）
3. `tenant.expired_at` 不为 NULL 且已过期 → 返回 403（租户已到期）
