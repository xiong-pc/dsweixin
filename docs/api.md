# API 接口文档

> **Base URL：** `http://localhost:8000/api/v1`
>
> **认证方式：** Bearer Token（登录后获取，在 `Authorization: Bearer <token>` 请求头中传递）
>
> **统一响应格式：**
> ```json
> { "code": 200, "msg": "Operation successful", "data": {} }
> ```
>
> **分页响应格式（data 字段）：**
> ```json
> { "list": [], "total": 100, "page": 1, "pageSize": 10 }
> ```

---

## 目录

- [认证模块](#认证模块)
- [用户管理](#用户管理)
- [角色管理](#角色管理)
- [菜单管理](#菜单管理)
- [部门管理](#部门管理)
- [字典管理](#字典管理)
- [字典项管理](#字典项管理)
- [系统配置](#系统配置)
- [公告管理](#公告管理)
- [租户管理](#租户管理)
- [错误码说明](#错误码说明)

---

## 认证模块

### POST /auth/login

用户名密码登录，返回 Access Token。

**无需认证**

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 登录用户名 |
| password | string | 是 | 登录密码 |

**请求示例：**
```json
{ "username": "admin", "password": "123456" }
```

**成功响应（200）：**
```json
{
  "code": 200,
  "msg": "Login successful",
  "data": {
    "accessToken": "eyJ0eXAiOiJKV1Q...",
    "tokenType": "Bearer",
    "expiresIn": 1296000
  }
}
```

**失败响应：**

| code | msg | 说明 |
|------|-----|------|
| 400 | Invalid credentials | 用户名或密码错误 |
| 403 | Account is disabled | 账号已被禁用 |
| 422 | — | 参数验证失败 |

---

### POST /auth/refresh

刷新 Token（Token 轮换，旧 Token 立即吊销）。

**需在请求头携带旧 Token（`Authorization: Bearer <token>`）**，路由本身无 `auth:api` 中间件，由接口内部手动解析。

**请求体：** 无

**成功响应（200）：**
```json
{
  "code": 200,
  "msg": "Operation successful",
  "data": {
    "accessToken": "eyJ0eXAiOiJKV1Q...",
    "tokenType": "Bearer",
    "expiresIn": 1296000
  }
}
```

---

### POST /auth/logout

登出，吊销当前 Token。

**需认证**

**请求体：** 无

**成功响应（200）：**
```json
{ "code": 200, "msg": "Logout successful", "data": null }
```

---

### GET /auth/me

获取当前登录用户信息及权限列表。

**需认证**

**成功响应（200）：**
```json
{
  "code": 200,
  "msg": "Operation successful",
  "data": {
    "userId": 1,
    "username": "admin",
    "nickname": "管理员",
    "avatar": "",
    "email": "admin@example.com",
    "phone": "13800000001",
    "gender": 1,
    "status": 1,
    "deptName": "技术部",
    "roles": ["ADMIN"],
    "permissions": ["sys:user:add", "sys:user:edit", "sys:user:delete"]
  }
}
```

> 超级管理员的 `permissions` 返回 `["*"]`，表示拥有全部权限。

---

### GET /auth/routes

获取当前用户有权限访问的路由菜单树（用于前端动态路由生成）。

**需认证**

**成功响应（200）：**
```json
{
  "code": 200,
  "msg": "Operation successful",
  "data": [
    {
      "path": "/system",
      "component": "Layout",
      "name": "系统管理",
      "redirect": "/system/user",
      "meta": { "title": "系统管理", "icon": "Setting", "hidden": false },
      "children": [
        {
          "path": "user",
          "component": "system/user/index",
          "name": "用户管理",
          "redirect": null,
          "meta": { "title": "用户管理", "icon": "User", "hidden": false }
        }
      ]
    }
  ]
}
```

---

## 用户管理

> 所有接口需认证 + 租户中间件（`auth:api` + `tenant`）

### GET /users

分页查询用户列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 模糊搜索用户名、昵称、手机号 |
| status | integer | 否 | 状态筛选：1=正常，0=禁用 |
| dept_id | integer | 否 | 部门 ID 筛选 |
| pageSize | integer | 否 | 每页条数，默认 10 |
| pageNum | integer | 否 | 页码，默认 1（使用 `page` 参数） |

**成功响应（200）：**
```json
{
  "code": 200,
  "msg": "Operation successful",
  "data": {
    "list": [
      {
        "id": 1, "tenantId": 1, "deptId": 1,
        "username": "admin", "nickname": "管理员",
        "email": "admin@example.com", "phone": "13800000001",
        "avatar": "", "gender": 1, "status": 1,
        "deptName": "技术部",
        "roles": [{ "id": 2, "name": "系统管理员", "code": "ADMIN" }],
        "createdAt": "2024-01-01 00:00:00"
      }
    ],
    "total": 1, "page": 1, "pageSize": 10
  }
}
```

---

### POST /users

创建用户。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名（全局唯一） |
| nickname | string | 是 | 昵称 |
| password | string | 是 | 密码（至少6位） |
| email | string | 否 | 邮箱 |
| phone | string | 否 | 手机号 |
| avatar | string | 否 | 头像 URL |
| gender | integer | 否 | 性别：0=未知，1=男，2=女 |
| status | integer | 否 | 状态：1=正常（默认），0=禁用 |
| dept_id | integer | 否 | 部门 ID |
| roleIds | array | 否 | 角色 ID 列表，如 `[1, 2]` |

**成功响应（200）：** 返回新建用户信息

---

### GET /users/{id}

获取单个用户详情（含角色和部门信息）。

---

### PUT /users/{id}

更新用户信息。

**请求体：** 同 POST，所有字段均为选填（`sometimes`），不传 `password`。

---

### DELETE /users/{id}

删除用户（物理删除）。

---

### PATCH /users/{id}/status

更新用户启用 / 禁用状态。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | integer | 是 | 1=启用，0=禁用 |

---

### POST /users/{id}/reset-password

重置用户密码为默认密码 `123456`。

**请求体：** 无

---

## 角色管理

### GET /roles

分页查询角色列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 模糊搜索角色名称或编码 |
| pageSize | integer | 否 | 每页条数，默认 10 |

**成功响应（200）：**
```json
{
  "code": 200, "msg": "Operation successful",
  "data": {
    "list": [
      {
        "id": 2, "tenantId": 1, "name": "系统管理员", "code": "ADMIN",
        "dataScope": 0, "sort": 2, "status": 1, "remark": "租户管理员",
        "menuIds": [1, 2, 3]
      }
    ],
    "total": 1, "page": 1, "pageSize": 10
  }
}
```

---

### POST /roles

创建角色（同时可指定菜单权限）。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 角色名称 |
| code | string | 是 | 角色编码，全局唯一 |
| data_scope | integer | 否 | 数据权限：0=全部，1=本部门，2=本部门及子部门，3=仅本人，4=自定义 |
| sort | integer | 否 | 排序 |
| status | integer | 否 | 状态：1=正常（默认），0=禁用 |
| remark | string | 否 | 备注 |
| menuIds | array | 否 | 菜单 ID 列表 |

---

### GET /roles/{id}

获取角色详情（含已绑定的菜单）。

---

### PUT /roles/{id}

更新角色（可同步更新菜单绑定）。

---

### DELETE /roles/{id}

删除角色（同时解除菜单和用户关联）。

---

### GET /roles/{id}/menus

获取角色已绑定的菜单 ID 列表。

**成功响应（200）：**
```json
{ "code": 200, "msg": "Operation successful", "data": [1, 2, 3, 10] }
```

---

### PUT /roles/{id}/menus

更新角色菜单绑定（全量替换）。

**请求体：**
```json
{ "menuIds": [1, 2, 3, 10] }
```

---

## 菜单管理

### GET /menus

获取菜单树（全量，不分页）。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 菜单名称模糊搜索 |

**成功响应（200）：**
```json
{
  "code": 200, "msg": "Operation successful",
  "data": [
    {
      "id": 1, "parentId": 0, "name": "系统管理", "type": 1,
      "path": "/system", "component": "Layout", "permission": "",
      "icon": "Setting", "sort": 1, "visible": 1, "redirect": "/system/user",
      "children": [
        {
          "id": 2, "parentId": 1, "name": "用户管理", "type": 2,
          "path": "user", "component": "system/user/index",
          "permission": "", "icon": "User", "sort": 1, "visible": 1, "redirect": ""
        }
      ]
    }
  ]
}
```

---

### POST /menus

创建菜单。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 菜单名称 |
| type | integer | 是 | 类型：1=目录，2=菜单，3=按钮，4=外链 |
| parent_id | integer | 否 | 父菜单 ID，0=根节点 |
| path | string | 否 | 路由路径 |
| component | string | 否 | 组件路径 |
| permission | string | 否 | 权限标识（type=3 时填写） |
| icon | string | 否 | 图标名称 |
| sort | integer | 否 | 排序 |
| visible | boolean | 否 | 是否显示，默认 true |
| redirect | string | 否 | 重定向路径 |

---

### GET /menus/{id}

获取菜单详情。

---

### PUT /menus/{id}

更新菜单。

---

### DELETE /menus/{id}

删除菜单（有子菜单时返回错误）。

---

## 部门管理

### GET /depts

获取部门树（全量，不分页）。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 部门名称模糊搜索 |
| status | integer | 否 | 状态筛选 |

**成功响应（200）：**
```json
{
  "code": 200, "msg": "Operation successful",
  "data": [
    {
      "id": 1, "tenantId": 1, "parentId": 0, "name": "总公司",
      "sort": 1, "status": 1,
      "children": [
        { "id": 2, "parentId": 1, "name": "技术部", "sort": 1, "status": 1 }
      ]
    }
  ]
}
```

---

### POST /depts

创建部门。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 部门名称 |
| parent_id | integer | 否 | 父部门 ID，默认 0 |
| sort | integer | 否 | 排序 |
| status | integer | 否 | 状态：1=正常（默认），0=禁用 |

---

### GET /depts/{id}

获取部门详情。

---

### PUT /depts/{id}

更新部门。

---

### DELETE /depts/{id}

删除部门（有子部门时返回错误）。

---

## 字典管理

### GET /dicts

分页查询字典列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 模糊搜索字典名称或编码 |
| pageSize | integer | 否 | 每页条数，默认 10 |

---

### POST /dicts

创建字典。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 字典名称 |
| code | string | 是 | 字典编码（全局唯一） |
| status | integer | 否 | 状态 |
| remark | string | 否 | 备注 |

---

### GET /dicts/{id}

获取字典详情（含字典项列表）。

---

### PUT /dicts/{id}

更新字典。

---

### DELETE /dicts/{id}

删除字典（级联删除所有字典项）。

---

### GET /dicts/{id}/items

获取指定字典的字典项列表（不分页）。

---

## 字典项管理

### POST /dict-items

创建字典项。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| dict_id | integer | 是 | 所属字典 ID |
| label | string | 是 | 显示标签 |
| value | string | 是 | 存储值 |
| sort | integer | 否 | 排序 |
| status | integer | 否 | 状态 |
| remark | string | 否 | 备注 |

---

### GET /dict-items/{id}

获取字典项详情。

---

### PUT /dict-items/{id}

更新字典项。

**请求体：** 同 POST，所有字段选填（不含 `dict_id`）。

---

### DELETE /dict-items/{id}

删除字典项。

---

## 系统配置

### GET /configs

分页查询配置列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 模糊搜索配置名称或键名 |
| name | string | 否 | 精确模糊搜索名称 |
| key | string | 否 | 精确模糊搜索键名 |
| pageSize | integer | 否 | 每页条数，默认 10 |
| pageNum | integer | 否 | 页码，默认 1 |

---

### POST /configs

创建配置项。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 配置名称 |
| key | string | 是 | 配置键（租户内唯一） |
| value | string | 否 | 配置值 |
| type | integer | 否 | 值类型：0=字符串，1=数字，2=布尔，3=JSON |
| remark | string | 否 | 备注 |

---

### GET /configs/{id}

获取配置项详情。

---

### PUT /configs/{id}

更新配置项。

---

### DELETE /configs/{id}

删除配置项。

---

## 公告管理

### GET /notices

分页查询公告列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 标题模糊搜索 |
| title | string | 否 | 标题模糊搜索 |
| type | integer | 否 | 类型：1=通知，2=公告 |
| status | integer | 否 | 状态：0=草稿，1=已发布，2=已撤回 |
| pageSize | integer | 否 | 每页条数，默认 10 |
| pageNum | integer | 否 | 页码，默认 1 |

---

### POST /notices

创建公告。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | string | 是 | 标题 |
| type | integer | 否 | 类型：1=通知（默认），2=公告 |
| level | integer | 否 | 级别：0=普通（默认），1=重要，2=紧急 |
| content | string | 否 | 正文内容 |
| status | integer | 否 | 初始状态：0=草稿（默认），1=直接发布 |

> 创建时 `status=1` 会自动设置 `publish_time`。

---

### GET /notices/{id}

获取公告详情（含发布人信息）。

---

### PUT /notices/{id}

更新公告（仅更新 title / type / level / content，不能通过此接口改变发布状态）。

---

### DELETE /notices/{id}

删除公告。

---

### PATCH /notices/{id}/publish

发布公告，将状态从草稿切换为已发布，并记录发布时间。

---

### PATCH /notices/{id}/revoke

撤回公告，将状态切换为已撤回。

---

## 租户管理

> 列表和详情接口要求用户拥有"租户管理"菜单权限；删除接口仅超级管理员可操作。

### GET /tenants

分页查询租户列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keywords | string | 否 | 模糊搜索租户名称或编码 |
| name | string | 否 | 租户名称模糊搜索 |
| status | integer | 否 | 状态筛选 |
| pageSize | integer | 否 | 每页条数，默认 10 |
| pageNum | integer | 否 | 页码，默认 1 |

> 非超级管理员只能看到自己所属租户。

---

### POST /tenants

创建租户（仅超级管理员，由 FormRequest.authorize 校验）。

**请求体：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 租户名称 |
| code | string | 是 | 租户编码（全局唯一） |
| status | integer | 否 | 状态：1=正常（默认），0=禁用 |
| contact_name | string | 否 | 联系人 |
| contact_phone | string | 否 | 联系电话 |
| expired_at | string | 否 | 过期时间，如 `2027-12-31 23:59:59` |
| remark | string | 否 | 备注 |

---

### GET /tenants/{id}

获取租户详情（非超管只能查看自己的租户）。

---

### PUT /tenants/{id}

更新租户（超管可更新任意；租户管理员只能更新自己租户，且需要 `sys:tenant:edit` 权限）。

---

### DELETE /tenants/{id}

删除租户（仅超级管理员，软删除）。

---

## 错误码说明

| HTTP 状态码 | code | 含义 |
|------------|------|------|
| 200 | 200 | 成功 |
| 400 | 400 | 请求错误（如用户名密码错误） |
| 401 | 401 | 未认证或 Token 失效 |
| 403 | 403 | 无权限（账号禁用 / 租户禁用 / 权限不足） |
| 404 | 404 | 资源不存在 |
| 422 | 422 | 请求参数验证失败（附带 `errors` 字段） |
| 500 | 500 | 服务器内部错误 |

**422 响应示例：**
```json
{
  "message": "The username field is required.",
  "errors": {
    "username": ["The username field is required."]
  }
}
```
