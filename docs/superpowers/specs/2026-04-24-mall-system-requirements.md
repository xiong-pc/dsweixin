# dsweixin 商城系统需求文档

## 项目架构说明（必读）

- 后端：Laravel 13，控制器统一放在 `backend/app/Http/Controllers/Api/`
- 路由前缀：`/api/v1/`，所有业务接口加 `tenant` 中间件
- 认证：Laravel Passport，Bearer Token
- 前端：Vue 3 + TypeScript，视图放在 `frontend/src/views/`
- 参考现有控制器写法：`UserController.php`、`DictController.php`
- 数据库表统一加 `tenant_id` 字段实现多租户隔离
- 所有表包含 `created_at`、`updated_at`、`deleted_at`（软删除）

---

## 模块一：商城概括（Dashboard）

### 前端
- 路由：`/mall/dashboard`
- 视图：`frontend/src/views/mall/dashboard/index.vue`
- 展示统计卡片：
  - 今日订单数 / 总订单数
  - 今日销售额 / 总销售额
  - 商品总数 / 库存预警数量
  - 待发货订单数 / 配送中订单数
- 折线图：近7天订单趋势、销售额趋势
- 表格：最新10条订单（订单号、商品、金额、状态）

### 后端
- 控制器：`MallDashboardController.php`
- 接口：`GET /api/v1/mall/dashboard/stats`
  - 返回所有统计数据（订单数、销售额、商品数、库存预警）
- 接口：`GET /api/v1/mall/dashboard/order-trend`
  - 参数：`days=7`（近N天）
  - 返回每日订单数和销售额数组
- 接口：`GET /api/v1/mall/dashboard/recent-orders`
  - 返回最新10条订单列表

---

## 模块二：商品管理

### 2.1 商品分类

#### 数据库表：`product_categories`
```
id, tenant_id, parent_id(默认0), name, icon, sort(排序), status(1启用0禁用),
created_at, updated_at, deleted_at
```

#### 后端
- 控制器：`ProductCategoryController.php`
- `GET    /api/v1/product/categories`       列表（支持树形结构参数 tree=1）
- `POST   /api/v1/product/categories`       新增
- `PUT    /api/v1/product/categories/{id}`  编辑
- `DELETE /api/v1/product/categories/{id}`  删除
- `PUT    /api/v1/product/categories/{id}/status` 切换状态

#### 前端
- 路由：`/mall/product/category`
- 视图：`frontend/src/views/mall/product/category/index.vue`（已有雏形，完善它）
- 树形表格展示，支持新增/编辑/删除/启用禁用

---

### 2.2 商品列表

#### 数据库表：`products`
```
id, tenant_id, category_id, name, cover_image, images(JSON图片数组),
description, price(decimal 10,2), original_price(decimal 10,2),
stock(总库存), unit(单位，如"件"), status(1上架0下架),
sort, sales(销量), created_at, updated_at, deleted_at
```

#### 数据库表：`product_skus`（规格/SKU）
```
id, tenant_id, product_id, sku_name(规格名如"红色-XL"),
price(decimal 10,2), stock, image, sort,
created_at, updated_at, deleted_at
```

#### 后端
- 控制器：`ProductController.php`
- `GET    /api/v1/products`              列表（分页，支持按分类/名称/状态筛选）
- `POST   /api/v1/products`             新增（含SKU数组）
- `GET    /api/v1/products/{id}`        详情
- `PUT    /api/v1/products/{id}`        编辑（含SKU数组）
- `DELETE /api/v1/products/{id}`        删除（软删除）
- `PUT    /api/v1/products/{id}/status` 上下架切换

#### 前端
- 路由：`/mall/product/list`
- 视图：`frontend/src/views/mall/product/list/index.vue`
- 搜索栏：分类下拉、商品名称、状态
- 表格列：封面图、商品名、分类、价格、库存、销量、状态、操作
- 新增/编辑弹窗：基本信息 + 图片上传 + SKU 规格设置（动态添加行）

---

### 2.3 库存管理

#### 数据库表：`stock_logs`（库存变动记录）
```
id, tenant_id, product_id, sku_id(可空), type(in入库/out出库),
quantity, before_stock, after_stock, remark, created_at, updated_at
```

#### 后端
- 控制器：`StockController.php`
- `GET  /api/v1/stock/logs`           库存变动记录（分页，按商品/时间筛选）
- `POST /api/v1/stock/adjust`         手动调整库存（入库/出库）
- `GET  /api/v1/stock/warning`        库存预警列表（库存低于10的商品）

#### 前端
- 路由：`/mall/product/stock`
- 视图：`frontend/src/views/mall/product/stock/index.vue`
- Tab切换：库存变动记录 / 库存预警

---

## 模块三：订单管理

### 3.1 订单列表

#### 数据库表：`orders`
```
id, tenant_id, order_no(唯一订单号), user_id, user_name, user_phone,
total_amount(decimal 10,2), pay_amount(decimal 10,2), freight(运费 decimal 10,2),
pay_type(1微信2支付宝3余额), pay_time,
status(0待付款 1待发货 2配送中 3已完成 4已取消 5退款中 6已退款),
remark(买家备注), admin_remark(商家备注),
receiver_name, receiver_phone, receiver_province, receiver_city,
receiver_district, receiver_address, receiver_postcode,
created_at, updated_at, deleted_at
```

#### 数据库表：`order_items`（订单商品明细）
```
id, tenant_id, order_id, product_id, sku_id(可空), product_name,
sku_name, cover_image, price(decimal 10,2), quantity, subtotal(decimal 10,2),
created_at, updated_at
```

#### 后端
- 控制器：`OrderController.php`
- `GET    /api/v1/orders`              订单列表（分页，按状态/订单号/用户/时间筛选）
- `GET    /api/v1/orders/{id}`         订单详情（含商品明细、配送信息）
- `PUT    /api/v1/orders/{id}/cancel`  取消订单
- `PUT    /api/v1/orders/{id}/remark`  修改商家备注
- `PUT    /api/v1/orders/{id}/refund`  确认退款

#### 前端
- 路由：`/mall/order/list`
- 视图：`frontend/src/views/mall/order/list/index.vue`
- Tab页签：全部 / 待付款 / 待发货 / 配送中 / 已完成 / 已取消 / 退款
- 表格列：订单号、买家、商品、金额、支付方式、状态、下单时间、操作
- 操作：查看详情、取消订单、备注、确认退款
- 详情弹窗：订单信息 + 商品明细 + 收货地址 + 配送信息

---

### 3.2 退款管理

#### 数据库表：`refunds`
```
id, tenant_id, order_id, order_no, user_id, amount(decimal 10,2),
reason(退款原因), images(JSON凭证图片), status(0待处理 1同意 2拒绝),
admin_remark, processed_at, created_at, updated_at
```

#### 后端
- 控制器：`RefundController.php`
- `GET  /api/v1/refunds`              退款申请列表（分页）
- `GET  /api/v1/refunds/{id}`         退款详情
- `PUT  /api/v1/refunds/{id}/approve` 同意退款
- `PUT  /api/v1/refunds/{id}/reject`  拒绝退款（需填写理由）

#### 前端
- 路由：`/mall/order/refund`
- 视图：`frontend/src/views/mall/order/refund/index.vue`
- 列表展示退款申请，支持同意/拒绝操作

---

## 模块四：配送管理

### 4.1 配送员管理

#### 数据库表：`deliverymen`
```
id, tenant_id, name, phone, avatar, status(1在职0离职),
current_orders(当前配送单数), total_delivered(累计完成数),
created_at, updated_at, deleted_at
```

#### 后端
- 控制器：`DeliverymanController.php`
- `GET    /api/v1/deliverymen`              列表（分页）
- `POST   /api/v1/deliverymen`             新增配送员
- `PUT    /api/v1/deliverymen/{id}`        编辑
- `DELETE /api/v1/deliverymen/{id}`        删除
- `PUT    /api/v1/deliverymen/{id}/status` 切换在职状态

#### 前端
- 路由：`/mall/delivery/man`
- 视图：`frontend/src/views/mall/delivery/man/index.vue`
- 表格：姓名、手机号、当前单数、累计完成、状态、操作

---

### 4.2 配送单管理

#### 数据库表：`delivery_orders`
```
id, tenant_id, order_id, order_no, deliveryman_id, deliveryman_name,
deliveryman_phone, receiver_name, receiver_phone, receiver_address,
status(0待分配 1已分配 2配送中 3已送达 4异常),
assigned_at, picked_at, delivered_at, exception_remark,
created_at, updated_at
```

#### 后端
- 控制器：`DeliveryOrderController.php`
- `GET  /api/v1/delivery/orders`                     配送单列表（分页，按状态/配送员/时间筛选）
- `GET  /api/v1/delivery/orders/{id}`                配送单详情
- `POST /api/v1/delivery/orders/{id}/assign`         分配配送员（参数：deliveryman_id）
- `PUT  /api/v1/delivery/orders/{id}/pickup`         确认取货（状态改为配送中）
- `PUT  /api/v1/delivery/orders/{id}/delivered`      确认送达
- `PUT  /api/v1/delivery/orders/{id}/exception`      标记异常（需填写备注）
- `GET  /api/v1/deliverymen/{id}/orders`             某配送员的配送记录

#### 前端
- 路由：`/mall/delivery/order`
- 视图：`frontend/src/views/mall/delivery/order/index.vue`
- Tab：待分配 / 配送中 / 已送达 / 异常
- 操作：分配配送员（弹窗选择）、查看详情、标记送达/异常

---

## 菜单结构（需在菜单管理中添加）

```
商城管理 (mall)
├── 商城概括        /mall/dashboard
├── 商品管理
│   ├── 商品分类    /mall/product/category
│   ├── 商品列表    /mall/product/list
│   └── 库存管理    /mall/product/stock
├── 订单管理
│   ├── 订单列表    /mall/order/list
│   └── 退款管理    /mall/order/refund
└── 配送管理
    ├── 配送员管理  /mall/delivery/man
    └── 配送单管理  /mall/delivery/order
```

---

## 开发优先级

1. 商品分类（基础数据）
2. 商品列表 + SKU
3. 订单列表 + 详情
4. 配送单管理 + 分配
5. 退款管理
6. 配送员管理
7. 库存管理
8. 商城概括 Dashboard

---

## 技术约定

- 所有 Controller 继承 `App\Http\Controllers\Api\Controller`
- 返回格式统一用现有的 `success()`/`error()` 响应方法
- 列表接口统一支持 `page` 和 `per_page` 分页参数
- 前端 API 调用统一放在 `frontend/src/api/mall/` 目录下
- 前端组件使用 Element Plus，风格参考现有 system 模块页面
