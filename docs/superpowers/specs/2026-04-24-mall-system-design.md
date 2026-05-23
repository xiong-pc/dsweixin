# 商城系统设计文档

**日期**：2026-04-24  
**项目**：dsweixin 全栈管理后台  
**状态**：已确认，待实现

---

## 一、需求概述

在现有多租户管理后台基础上，新增商城系统，支持多商家入驻（B 类平台），提供以下四大功能模块：

- **概括**：商城核心数据统计看板
- **商品管理**：商家入驻审核 + 商品全生命周期管理
- **订单管理**：订单流转 + 退款售后 + 支付记录
- **配送管理**：自配送派单 + 第三方快递追踪

**关键约束：**
- 商品类型：实物 + 虚拟 + 服务类
- 支付方式：微信支付、支付宝（在线）+ 线下/对公转账
- 配送方式：平台自配（配送员） + 第三方快递
- 租户隔离：每个租户有独立商城，数据完全隔离

---

## 二、整体架构

采用**域分离方案**：在现有 Laravel 13 + Vue 3 项目内，商城代码按域归类，复用现有 tenant 中间件、认证和 RBAC 权限体系。

### 后端结构

```
app/Http/Controllers/Api/Mall/
├── OverviewController.php       # 概括统计
├── MerchantController.php       # 商家管理
├── ProductController.php        # 商品管理
├── ProductSkuController.php     # 规格/SKU 管理
├── OrderController.php          # 订单管理
├── RefundController.php         # 退款售后
├── PaymentController.php        # 支付记录
├── DeliveryController.php       # 配送单管理
└── DeliveryStaffController.php  # 配送员管理

app/Models/Mall/
├── Merchant.php
├── Product.php
├── ProductSku.php
├── Order.php
├── OrderItem.php
├── Payment.php
├── Delivery.php
└── DeliveryStaff.php
```

### 前端结构

```
src/views/mall/
├── overview/index.vue
├── merchant/index.vue
├── product/index.vue
├── order/index.vue
├── refund/index.vue
├── payment/index.vue
├── delivery/index.vue
└── delivery-staff/index.vue
```

---

## 三、数据模型

### mall_merchants（商家）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| tenant_id | bigint | 租户 ID |
| name | string | 商家名称 |
| logo | string | 商家 logo |
| contact_name | string | 联系人 |
| contact_phone | string | 联系电话 |
| status | enum | pending/active/disabled |
| commission_rate | decimal | 佣金比例 |
| created_at | timestamp | 创建时间 |

### mall_products（商品）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| tenant_id | bigint | 租户 ID |
| merchant_id | bigint | 所属商家 |
| category_id | bigint | 分类（复用现有） |
| name | string | 商品名称 |
| type | enum | physical/virtual/service |
| cover | string | 封面图 |
| description | text | 详情描述 |
| status | enum | draft/pending/active/off_shelf |
| sort | int | 排序 |

### mall_product_skus（规格/SKU）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| product_id | bigint | 所属商品 |
| spec_values | json | 规格值（如：颜色:红,尺寸:L） |
| price | decimal | 售价 |
| stock | int | 库存（虚拟/服务类为 -1 不限） |
| code | string | SKU 编码 |

### mall_orders（订单）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| tenant_id | bigint | 租户 ID |
| merchant_id | bigint | 所属商家 |
| order_no | string | 订单号（唯一） |
| buyer_name | string | 买家姓名 |
| buyer_phone | string | 买家电话 |
| total_amount | decimal | 商品总金额 |
| pay_amount | decimal | 实付金额 |
| status | enum | pending/paid/shipped/done/cancelled |
| payment_method | enum | wechat/alipay/offline |
| paid_at | timestamp | 支付时间 |

### mall_order_items（订单明细）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| order_id | bigint | 订单 ID |
| product_id | bigint | 商品 ID |
| sku_id | bigint | SKU ID |
| product_name | string | 快照：商品名 |
| spec_values | json | 快照：规格值 |
| price | decimal | 快照：单价 |
| quantity | int | 数量 |
| subtotal | decimal | 小计 |

### mall_payments（支付记录）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| order_id | bigint | 订单 ID |
| method | enum | wechat/alipay/offline |
| amount | decimal | 支付金额 |
| trade_no | string | 第三方交易号 |
| status | enum | pending/success/failed/refunded |
| paid_at | timestamp | 支付时间 |

### mall_refunds（退款单）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| order_id | bigint | 订单 ID |
| order_item_id | bigint | 退款明细 ID（可选，支持部分退款） |
| amount | decimal | 退款金额 |
| reason | string | 退款原因 |
| status | enum | pending/approved/rejected/done |
| reject_reason | string | 拒绝原因（驳回时填写） |
| created_at | timestamp | 申请时间 |

### mall_deliveries（配送单）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| order_id | bigint | 订单 ID |
| type | enum | self/express |
| staff_id | bigint | 配送员 ID（自配时） |
| express_company | string | 快递公司（快递时） |
| tracking_no | string | 快递单号（快递时） |
| status | enum | pending/assigned/shipping/done |

### mall_delivery_staff（配送员）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| tenant_id | bigint | 租户 ID |
| name | string | 姓名 |
| phone | string | 手机号 |
| status | enum | active/inactive |

---

## 四、API 路由

在现有 `tenant` 中间件组下追加：

```php
Route::prefix('mall')->group(function () {
    // 概括
    Route::get('overview', [OverviewController::class, 'index']);

    // 商家
    Route::apiResource('merchants', MerchantController::class);
    Route::patch('merchants/{merchant}/status', [MerchantController::class, 'updateStatus']);

    // 商品
    Route::apiResource('products', ProductController::class);
    Route::patch('products/{product}/status', [ProductController::class, 'updateStatus']);
    Route::apiResource('products.skus', ProductSkuController::class)->shallow();

    // 订单
    Route::apiResource('orders', OrderController::class)->except(['store', 'destroy']);
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel']);

    // 退款
    Route::apiResource('refunds', RefundController::class)->except(['store']);
    Route::patch('refunds/{refund}/approve', [RefundController::class, 'approve']);
    Route::patch('refunds/{refund}/reject', [RefundController::class, 'reject']);

    // 支付
    Route::get('payments', [PaymentController::class, 'index']);

    // 配送
    Route::apiResource('deliveries', DeliveryController::class)->except(['store', 'destroy']);
    Route::patch('deliveries/{delivery}/assign', [DeliveryController::class, 'assign']);
    Route::apiResource('delivery-staff', DeliveryStaffController::class);
});
```

---

## 五、前端页面

| 菜单模块 | 路由 | 页面功能 |
|---------|------|---------|
| 概括 | `/mall/overview` | GMV、订单数、商家数、待审核商品数统计卡片 + 近7日折线图 |
| 商品管理 > 商家管理 | `/mall/merchant` | 商家列表、状态审核（pending/active/disabled） |
| 商品管理 > 商品列表 | `/mall/product` | 商品列表、类型筛选、状态管理（复用已有商品分类） |
| 订单管理 > 订单列表 | `/mall/order` | 订单列表、状态筛选、详情抽屉 |
| 订单管理 > 退款售后 | `/mall/refund` | 退款单列表、审批操作 |
| 订单管理 > 支付记录 | `/mall/payment` | 支付流水查询 |
| 配送管理 > 配送单 | `/mall/delivery` | 配送单列表、派单（自配）/ 填写快递单号（快递） |
| 配送管理 > 配送员 | `/mall/delivery-staff` | 配送员列表、状态管理 |

---

## 六、权限命名规范

```
mall:merchant:list / add / edit / delete / status
mall:product:list / add / edit / delete / status
mall:order:list / detail / cancel
mall:refund:list / approve / reject
mall:payment:list
mall:delivery:list / assign
mall:delivery-staff:list / add / edit / delete
```

---

## 七、关键约定

1. 所有 `mall_*` 表均含 `tenant_id`，由现有 `tenant` 中间件自动注入隔离
2. 商品分类：前端 `product/category` 页面已存在，但后端 Controller/Model/Migration 尚未实现，需在商城模块中一并创建（`ProductCategoryController`）
3. 订单明细快照商品名称和规格，防止商品修改后影响历史订单显示
4. 虚拟/服务类商品库存字段存 `-1` 表示不限量
5. 配送单由订单支付成功后系统自动创建，管理员再操作派单或填写快递单号
