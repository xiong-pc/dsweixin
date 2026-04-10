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
