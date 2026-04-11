# Frontend — Vue 3 + TypeScript

## 技术栈

- Vue 3 + TypeScript + Vite 7
- Element Plus（UI 组件库）
- Pinia（状态管理）
- Vue Router 4（动态路由，由后端权限菜单生成）
- Vue i18n（国际化）
- axios（HTTP 请求）

## 常用命令

```bash
npm install        # 安装依赖
npm run dev        # 启动开发服务器
npm run build      # 构建生产包
npm run type-check # TypeScript 类型检查
```

## 模块完成检查

每完成一个模块（新增页面、API、类型定义）后，必须执行：

```bash
npm run type-check
```

确认零错误后再提交。常见问题：
- 表单字段与 `StoreXxxRequest` 不一致
- `reactive()` 中字面量联合类型未用 `as` 标注
- `src/types/api/xxx.ts` 字段与实际接口不同步
- Element Plus 组件 prop 类型用法错误（如 `el-tree-select` 的 `value-key`）

## 目录约定

```
src/
├── api/           # 按模块封装的接口函数（对应后端 Controller）
├── views/         # 页面组件，按功能模块分目录
│   ├── system/    # 系统管理页面（user/role/menu/dept 等）
│   └── dashboard/ # 仪表盘
├── store/         # Pinia store（user/permission/app/tagsView）
├── router/        # 路由配置（constantRoutes + 动态路由）
├── components/    # 全局通用组件
├── composables/   # 组合式函数（use* 命名）
├── layout/        # 整体布局框架
├── types/         # TypeScript 类型定义
├── enums/         # 枚举常量
└── lang/          # i18n 语言包
```

## 关键模式

**动态路由**：登录后调用 `auth/routes` 获取权限菜单，`permission store` 将其转换为 Vue Router 路由并 `addRoute`，路由 guard 在 `router/guard.ts` 中处理。

**API 模块**：每个业务模块在 `src/api/` 下对应一个文件（如 `user.ts`），封装该模块的所有接口调用，统一通过 axios 实例发送请求。

**状态管理**：
- `user store` — 存储登录态、用户信息、token
- `permission store` — 存储动态路由和菜单树
- `app store` — 侧边栏折叠、设备类型等 UI 状态
- `tagsView store` — 标签页导航状态

**组件规范**：页面级组件放 `views/`，可复用组件放 `components/`，不要在 `views/` 内嵌套创建跨页面复用的组件。

## 编码约定

**API 调用**：所有接口调用必须封装在 `src/api/` 对应模块文件中，页面组件不允许直接调用 axios，通过模块函数调用。

```ts
// 正确
import { getUserList } from '@/api/user'
const { data } = await getUserList(params)
```

```ts
// 禁止 — 组件内直接调用 axios
import request from '@/utils/request'
const { data } = await request.get('/api/v1/users') // ❌
```

**TypeScript**：禁止使用 `any` 类型，接口响应结构在 `src/types/` 中定义对应类型。

**组合式函数**：抽取可复用逻辑到 `src/composables/`，以 `use` 开头命名（如 `useUserForm`），不要在多个页面组件中重复相同逻辑。

**Element Plus**：表单验证使用 `el-form` 的 `rules` 属性，不要在提交时手动校验字段；表格分页统一使用 `el-pagination` 组件，分页参数格式 `{ page, page_size }`。

**表单与 API 类型对齐**：表单 `reactive()` 对象的字段必须与对应的 `StoreXxxRequest` / `UpdateXxxRequest` 保持一致：

- 字段名、可选性要匹配，不要在表单里随意增删字段
- 字面量联合类型字段在 `reactive()` 初始化时用 `as` 标注：
  ```ts
  type: 1 as 1 | 2 | 3 | 4
  ```
- 表单专有字段（如 `id`）单独声明，不要混入请求类型
- 新增模块时同步维护 `src/types/api/xxx.ts`，确保类型与实际接口字段一致
