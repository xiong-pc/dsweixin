# 商城系统实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 dsweixin 多租户管理后台中新增完整商城系统，支持多商家入驻、实物/虚拟/服务类商品、在线+线下混合支付、自配+快递混合配送。

**Architecture:** 域分离方案——后端代码放在 `App\Http\Controllers\Api\Mall\`、`App\Models\Mall\`、`App\Services\Api\Mall\` 命名空间下，复用现有 `tenant` 中间件自动隔离数据；前端页面集中在 `src/views/mall/`。路由统一挂在 `/api/v1/mall/` 前缀下。

**Tech Stack:** Laravel 13 + Passport OAuth + spatie/laravel-permission + MySQL/SQLite(test) + Vue 3 + TypeScript + Element Plus + Pinia + Vue Router

**设计参考：** `docs/superpowers/specs/2026-04-24-mall-system-design.md`

---

## 任务总览

| # | 任务 | 粒度 |
|---|------|------|
| 1 | 数据库迁移（10 张表） | 后端 |
| 2 | Eloquent 模型（10 个） | 后端 |
| 3 | 商品分类（后端全栈） | 后端 |
| 4 | 商家管理（后端全栈） | 后端 |
| 5 | 商品管理（后端全栈，含 SKU） | 后端 |
| 6 | 订单管理（后端全栈） | 后端 |
| 7 | 退款售后（后端全栈） | 后端 |
| 8 | 支付记录（后端） | 后端 |
| 9 | 配送管理（后端全栈，含配送员） | 后端 |
| 10 | 概括统计（后端） | 后端 |
| 11 | 注册所有路由 | 后端 |
| 12 | 前端 API 封装 | 前端 |
| 13 | 商品分类页（更新现有） | 前端 |
| 14 | 商家管理页 | 前端 |
| 15 | 商品列表页 | 前端 |
| 16 | 订单管理页 | 前端 |
| 17 | 退款/支付记录页 | 前端 |
| 18 | 配送单/配送员页 | 前端 |
| 19 | 概括看板页 | 前端 |

---

## Task 1: 数据库迁移

**Files:**
- Create: `backend/database/migrations/2026_04_24_000001_create_mall_product_categories_table.php`
- Create: `backend/database/migrations/2026_04_24_000002_create_mall_merchants_table.php`
- Create: `backend/database/migrations/2026_04_24_000003_create_mall_products_table.php`
- Create: `backend/database/migrations/2026_04_24_000004_create_mall_product_skus_table.php`
- Create: `backend/database/migrations/2026_04_24_000005_create_mall_orders_table.php`
- Create: `backend/database/migrations/2026_04_24_000006_create_mall_order_items_table.php`
- Create: `backend/database/migrations/2026_04_24_000007_create_mall_payments_table.php`
- Create: `backend/database/migrations/2026_04_24_000008_create_mall_refunds_table.php`
- Create: `backend/database/migrations/2026_04_24_000009_create_mall_deliveries_table.php`
- Create: `backend/database/migrations/2026_04_24_000010_create_mall_delivery_staff_table.php`

- [ ] **Step 1.1: 创建 mall_product_categories 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000001_create_mall_product_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index()->comment('租户ID');
            $table->unsignedBigInteger('parent_id')->default(0)->index()->comment('上级分类ID');
            $table->string('name', 50)->default('')->comment('分类名称');
            $table->string('icon', 100)->default('')->comment('图标');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态(1:正常 0:禁用)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_product_categories');
    }
};
```

- [ ] **Step 1.2: 创建 mall_merchants 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000002_create_mall_merchants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_merchants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index()->comment('租户ID');
            $table->string('name', 80)->default('')->comment('商家名称');
            $table->string('logo', 255)->default('')->comment('Logo');
            $table->string('contact_name', 50)->default('')->comment('联系人');
            $table->string('contact_phone', 20)->default('')->comment('联系电话');
            $table->string('status', 20)->default('pending')->comment('pending/active/disabled');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('佣金比例(%)');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_merchants');
    }
};
```

- [ ] **Step 1.3: 创建 mall_products 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000003_create_mall_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index()->comment('租户ID');
            $table->unsignedBigInteger('merchant_id')->default(0)->index()->comment('商家ID');
            $table->unsignedBigInteger('category_id')->default(0)->index()->comment('分类ID');
            $table->string('name', 120)->default('')->comment('商品名称');
            $table->string('type', 20)->default('physical')->comment('physical/virtual/service');
            $table->string('cover', 255)->default('')->comment('封面图');
            $table->text('description')->nullable()->comment('详情');
            $table->string('status', 20)->default('draft')->comment('draft/pending/active/off_shelf');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_products');
    }
};
```

- [ ] **Step 1.4: 创建 mall_product_skus 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000004_create_mall_product_skus_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_product_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index()->comment('商品ID');
            $table->json('spec_values')->nullable()->comment('规格值 JSON');
            $table->decimal('price', 10, 2)->default(0)->comment('售价');
            $table->integer('stock')->default(0)->comment('库存(-1 不限)');
            $table->string('code', 50)->default('')->comment('SKU编码');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_product_skus');
    }
};
```

- [ ] **Step 1.5: 创建 mall_orders 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000005_create_mall_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index()->comment('租户ID');
            $table->unsignedBigInteger('merchant_id')->default(0)->index()->comment('商家ID');
            $table->string('order_no', 32)->unique()->comment('订单号');
            $table->string('buyer_name', 50)->default('')->comment('买家姓名');
            $table->string('buyer_phone', 20)->default('')->comment('买家电话');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('商品总金额');
            $table->decimal('pay_amount', 10, 2)->default(0)->comment('实付金额');
            $table->string('status', 20)->default('pending')->comment('pending/paid/shipped/done/cancelled');
            $table->string('payment_method', 20)->default('')->comment('wechat/alipay/offline');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_orders');
    }
};
```

- [ ] **Step 1.6: 创建 mall_order_items 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000006_create_mall_order_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('订单ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedBigInteger('sku_id')->default(0)->comment('SKU ID');
            $table->string('product_name', 120)->default('')->comment('快照:商品名');
            $table->json('spec_values')->nullable()->comment('快照:规格值');
            $table->decimal('price', 10, 2)->default(0)->comment('快照:单价');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('subtotal', 10, 2)->default(0)->comment('小计');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_order_items');
    }
};
```

- [ ] **Step 1.7: 创建 mall_payments 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000007_create_mall_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('订单ID');
            $table->string('method', 20)->default('')->comment('wechat/alipay/offline');
            $table->decimal('amount', 10, 2)->default(0)->comment('支付金额');
            $table->string('trade_no', 64)->default('')->comment('第三方交易号');
            $table->string('status', 20)->default('pending')->comment('pending/success/failed/refunded');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_payments');
    }
};
```

- [ ] **Step 1.8: 创建 mall_refunds 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000008_create_mall_refunds_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('订单ID');
            $table->unsignedBigInteger('order_item_id')->default(0)->comment('订单明细ID(0=整单退)');
            $table->decimal('amount', 10, 2)->default(0)->comment('退款金额');
            $table->string('reason', 255)->default('')->comment('退款原因');
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/done');
            $table->string('reject_reason', 255)->default('')->comment('拒绝原因');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_refunds');
    }
};
```

- [ ] **Step 1.9: 创建 mall_deliveries 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000009_create_mall_deliveries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('订单ID');
            $table->string('type', 20)->default('self')->comment('self/express');
            $table->unsignedBigInteger('staff_id')->default(0)->comment('配送员ID(自配)');
            $table->string('express_company', 50)->default('')->comment('快递公司(快递)');
            $table->string('tracking_no', 64)->default('')->comment('快递单号(快递)');
            $table->string('status', 20)->default('pending')->comment('pending/assigned/shipping/done');
            $table->timestamp('shipped_at')->nullable()->comment('发货时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_deliveries');
    }
};
```

- [ ] **Step 1.10: 创建 mall_delivery_staff 迁移**

```php
<?php
// backend/database/migrations/2026_04_24_000010_create_mall_delivery_staff_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_delivery_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index()->comment('租户ID');
            $table->string('name', 50)->default('')->comment('姓名');
            $table->string('phone', 20)->default('')->comment('手机号');
            $table->tinyInteger('status')->default(1)->comment('1:在岗 0:离岗');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_delivery_staff');
    }
};
```

- [ ] **Step 1.11: 运行迁移验证**

```bash
cd backend && php artisan migrate
```

Expected: 显示 10 条 `Migrating: ... DONE` 输出。

- [ ] **Step 1.12: 提交**

```bash
git add backend/database/migrations/2026_04_24_*.php
git commit -m "feat(mall): 新增商城 10 张数据表迁移"
```

---

## Task 2: Eloquent 模型

**Files:**
- Create: `backend/app/Models/Mall/ProductCategory.php`
- Create: `backend/app/Models/Mall/Merchant.php`
- Create: `backend/app/Models/Mall/Product.php`
- Create: `backend/app/Models/Mall/ProductSku.php`
- Create: `backend/app/Models/Mall/Order.php`
- Create: `backend/app/Models/Mall/OrderItem.php`
- Create: `backend/app/Models/Mall/Payment.php`
- Create: `backend/app/Models/Mall/Refund.php`
- Create: `backend/app/Models/Mall/Delivery.php`
- Create: `backend/app/Models/Mall/DeliveryStaff.php`

- [ ] **Step 2.1: ProductCategory 模型**

```php
<?php
// backend/app/Models/Mall/ProductCategory.php

namespace App\Models\Mall;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'mall_product_categories';

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'icon', 'sort', 'status',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
```

- [ ] **Step 2.2: Merchant 模型**

```php
<?php
// backend/app/Models/Mall/Merchant.php

namespace App\Models\Mall;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'mall_merchants';

    protected $fillable = [
        'tenant_id', 'name', 'logo', 'contact_name', 'contact_phone',
        'status', 'commission_rate', 'remark',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'merchant_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'merchant_id');
    }
}
```

- [ ] **Step 2.3: Product 模型**

```php
<?php
// backend/app/Models/Mall/Product.php

namespace App\Models\Mall;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'mall_products';

    protected $fillable = [
        'tenant_id', 'merchant_id', 'category_id', 'name',
        'type', 'cover', 'description', 'status', 'sort',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class, 'product_id');
    }
}
```

- [ ] **Step 2.4: ProductSku 模型**

```php
<?php
// backend/app/Models/Mall/ProductSku.php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{
    use HasFactory;

    protected $table = 'mall_product_skus';

    protected $fillable = [
        'product_id', 'spec_values', 'price', 'stock', 'code',
    ];

    protected $casts = [
        'spec_values' => 'array',
        'price'       => 'decimal:2',
        'stock'       => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
```

- [ ] **Step 2.5: Order 模型**

```php
<?php
// backend/app/Models/Mall/Order.php

namespace App\Models\Mall;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'mall_orders';

    protected $fillable = [
        'tenant_id', 'merchant_id', 'order_no', 'buyer_name', 'buyer_phone',
        'total_amount', 'pay_amount', 'status', 'payment_method', 'paid_at', 'remark',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'pay_amount'   => 'decimal:2',
        'paid_at'      => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class, 'order_id');
    }
}
```

- [ ] **Step 2.6: OrderItem / Payment / Refund / Delivery / DeliveryStaff 模型**

```php
<?php
// backend/app/Models/Mall/OrderItem.php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'mall_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'sku_id', 'product_name',
        'spec_values', 'price', 'quantity', 'subtotal',
    ];

    protected $casts = [
        'spec_values' => 'array',
        'price'       => 'decimal:2',
        'subtotal'    => 'decimal:2',
        'quantity'    => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
```

```php
<?php
// backend/app/Models/Mall/Payment.php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'mall_payments';

    protected $fillable = [
        'order_id', 'method', 'amount', 'trade_no', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
```

```php
<?php
// backend/app/Models/Mall/Refund.php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $table = 'mall_refunds';

    protected $fillable = [
        'order_id', 'order_item_id', 'amount', 'reason', 'status', 'reject_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
```

```php
<?php
// backend/app/Models/Mall/Delivery.php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'mall_deliveries';

    protected $fillable = [
        'order_id', 'type', 'staff_id', 'express_company', 'tracking_no',
        'status', 'shipped_at', 'completed_at',
    ];

    protected $casts = [
        'shipped_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function staff()
    {
        return $this->belongsTo(DeliveryStaff::class, 'staff_id');
    }
}
```

```php
<?php
// backend/app/Models/Mall/DeliveryStaff.php

namespace App\Models\Mall;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryStaff extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'mall_delivery_staff';

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
```

- [ ] **Step 2.7: 提交**

```bash
git add backend/app/Models/Mall/
git commit -m "feat(mall): 新增商城 10 个 Eloquent 模型"
```

---

## Task 3: 商品分类后端全栈

**Files:**
- Create: `backend/app/Services/Api/Mall/ProductCategoryService.php`
- Create: `backend/app/Http/Requests/Api/Mall/Category/StoreProductCategoryRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Category/UpdateProductCategoryRequest.php`
- Create: `backend/app/Http/Resources/Api/Mall/ProductCategoryResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/ProductCategoryController.php`
- Create: `backend/tests/Feature/Mall/ProductCategoryTest.php`

- [ ] **Step 3.1: 编写失败测试**

```php
<?php
// backend/tests/Feature/Mall/ProductCategoryTest.php

namespace Tests\Feature\Mall;

use App\Models\Mall\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    public function test_index_returns_tree(): void
    {
        ProductCategory::create(['tenant_id' => 1, 'parent_id' => 0, 'name' => '食品', 'status' => 1]);

        $response = $this->getJson('/api/v1/mall/categories');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => [['id', 'name', 'parent_id', 'children']]]);
    }

    public function test_store_creates_category(): void
    {
        $response = $this->postJson('/api/v1/mall/categories', [
            'parent_id' => 0,
            'name'      => '饮料',
            'icon'      => '',
            'sort'      => 1,
            'status'    => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('mall_product_categories', ['name' => '饮料']);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/mall/categories', []);
        $response->assertStatus(422);
    }

    public function test_update_modifies_category(): void
    {
        $c = ProductCategory::create(['tenant_id' => 1, 'parent_id' => 0, 'name' => '原名', 'status' => 1]);

        $response = $this->putJson("/api/v1/mall/categories/{$c->id}", ['name' => '新名']);

        $response->assertOk();
        $this->assertDatabaseHas('mall_product_categories', ['id' => $c->id, 'name' => '新名']);
    }

    public function test_destroy_rejects_when_has_children(): void
    {
        $parent = ProductCategory::create(['tenant_id' => 1, 'parent_id' => 0, 'name' => '父', 'status' => 1]);
        ProductCategory::create(['tenant_id' => 1, 'parent_id' => $parent->id, 'name' => '子', 'status' => 1]);

        $response = $this->deleteJson("/api/v1/mall/categories/{$parent->id}");

        $response->assertStatus(400);
    }
}
```

- [ ] **Step 3.2: 运行测试确认失败**

```bash
cd backend && php artisan test --filter=ProductCategoryTest
```

Expected: FAIL `Route [api/v1/mall/categories] not defined` 或 404。

- [ ] **Step 3.3: 实现 Service**

```php
<?php
// backend/app/Services/Api/Mall/ProductCategoryService.php

namespace App\Services\Api\Mall;

use App\Models\Mall\ProductCategory;
use Illuminate\Support\Collection;

class ProductCategoryService
{
    public function tree(?string $keyword = null): Collection
    {
        $query = ProductCategory::query()->orderBy('sort')->orderBy('id');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $all = $query->get();

        return $this->buildTree($all, 0);
    }

    private function buildTree(Collection $all, int $parentId): Collection
    {
        return $all->where('parent_id', $parentId)->values()->map(function ($item) use ($all) {
            $item->children = $this->buildTree($all, $item->id);
            return $item;
        });
    }

    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function update(ProductCategory $category, array $data): void
    {
        $category->update($data);
    }

    public function delete(ProductCategory $category): void
    {
        if (ProductCategory::where('parent_id', $category->id)->exists()) {
            abort(400, '存在子分类，无法删除');
        }
        $category->delete();
    }
}
```

- [ ] **Step 3.4: 实现 FormRequest**

```php
<?php
// backend/app/Http/Requests/Api/Mall/Category/StoreProductCategoryRequest.php

namespace App\Http\Requests\Api\Mall\Category;

use App\Http\Requests\Api\ApiFormRequest;

class StoreProductCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|min:0',
            'name'      => 'required|string|max:50',
            'icon'      => 'nullable|string|max:100',
            'sort'      => 'nullable|integer|min:0',
            'status'    => 'nullable|in:0,1',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Category/UpdateProductCategoryRequest.php

namespace App\Http\Requests\Api\Mall\Category;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateProductCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|min:0',
            'name'      => 'sometimes|required|string|max:50',
            'icon'      => 'nullable|string|max:100',
            'sort'      => 'nullable|integer|min:0',
            'status'    => 'nullable|in:0,1',
        ];
    }
}
```

- [ ] **Step 3.5: 实现 Resource**

```php
<?php
// backend/app/Http/Resources/Api/Mall/ProductCategoryResource.php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'parent_id'  => $this->parent_id,
            'name'       => $this->name,
            'icon'       => $this->icon,
            'sort'       => $this->sort,
            'status'     => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'children'   => $this->children ?? [],
        ];
    }
}
```

- [ ] **Step 3.6: 实现 Controller**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/ProductCategoryController.php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Category\StoreProductCategoryRequest;
use App\Http\Requests\Api\Mall\Category\UpdateProductCategoryRequest;
use App\Http\Resources\Api\Mall\ProductCategoryResource;
use App\Models\Mall\ProductCategory;
use App\Services\Api\Mall\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tree = $this->service->tree($request->input('name'));
        return $this->success(ProductCategoryResource::collection($tree)->resolve());
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->validated());
        return $this->success(new ProductCategoryResource($category), 'api.created');
    }

    public function show(ProductCategory $category): JsonResponse
    {
        return $this->success(new ProductCategoryResource($category));
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): JsonResponse
    {
        $this->service->update($category, $request->validated());
        return $this->success(null, 'api.updated');
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $this->service->delete($category);
        return $this->success(null, 'api.deleted');
    }
}
```

- [ ] **Step 3.7: 临时注册路由（Task 11 会集中整理）**

在 `backend/routes/api.php` 的 `tenant` 中间件组内追加：

```php
Route::prefix('mall')->group(function () {
    Route::apiResource('categories', \App\Http\Controllers\Api\Mall\ProductCategoryController::class);
});
```

注意：Laravel 的 `ProductCategory` 模型 route-model binding 会使用 `{category}` 参数名。控制器方法参数用 `ProductCategory $category` 即可。

- [ ] **Step 3.8: 运行测试确认通过**

```bash
cd backend && php artisan test --filter=ProductCategoryTest
```

Expected: PASS 5 个测试全部通过。

- [ ] **Step 3.9: 提交**

```bash
git add backend/app/Services/Api/Mall backend/app/Http/Requests/Api/Mall/Category \
  backend/app/Http/Resources/Api/Mall/ProductCategoryResource.php \
  backend/app/Http/Controllers/Api/Mall/ProductCategoryController.php \
  backend/tests/Feature/Mall/ProductCategoryTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增商品分类后端（含测试）"
```

---

## Task 4: 商家管理后端全栈

**Files:**
- Create: `backend/app/Services/Api/Mall/MerchantService.php`
- Create: `backend/app/Http/Requests/Api/Mall/Merchant/StoreMerchantRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Merchant/UpdateMerchantRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Merchant/UpdateMerchantStatusRequest.php`
- Create: `backend/app/Http/Resources/Api/Mall/MerchantResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/MerchantController.php`
- Create: `backend/tests/Feature/Mall/MerchantTest.php`

- [ ] **Step 4.1: 编写失败测试**

```php
<?php
// backend/tests/Feature/Mall/MerchantTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    public function test_index_returns_paginated_merchants(): void
    {
        Merchant::create(['tenant_id' => 1, 'name' => '测试商家', 'status' => 'active']);

        $response = $this->getJson('/api/v1/mall/merchants');
        $response->assertOk()->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_status(): void
    {
        Merchant::create(['tenant_id' => 1, 'name' => 'A', 'status' => 'active']);
        Merchant::create(['tenant_id' => 1, 'name' => 'B', 'status' => 'pending']);

        $response = $this->getJson('/api/v1/mall/merchants?status=pending');
        $this->assertCount(1, $response->json('data.list'));
    }

    public function test_store_creates_merchant(): void
    {
        $response = $this->postJson('/api/v1/mall/merchants', [
            'name' => '新商家', 'contact_name' => '张三', 'contact_phone' => '13800138000',
            'status' => 'pending', 'commission_rate' => 5,
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('mall_merchants', ['name' => '新商家']);
    }

    public function test_update_status(): void
    {
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'X', 'status' => 'pending']);

        $response = $this->patchJson("/api/v1/mall/merchants/{$m->id}/status", ['status' => 'active']);
        $response->assertOk();
        $this->assertDatabaseHas('mall_merchants', ['id' => $m->id, 'status' => 'active']);
    }

    public function test_destroy_deletes_merchant(): void
    {
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'Del', 'status' => 'disabled']);
        $this->deleteJson("/api/v1/mall/merchants/{$m->id}")->assertOk();
        $this->assertSoftDeleted('mall_merchants', ['id' => $m->id]);
    }
}
```

- [ ] **Step 4.2: 运行测试确认失败**

```bash
cd backend && php artisan test --filter=MerchantTest
```

Expected: 全部 FAIL。

- [ ] **Step 4.3: Service**

```php
<?php
// backend/app/Services/Api/Mall/MerchantService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Merchant;
use Illuminate\Pagination\LengthAwarePaginator;

class MerchantService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Merchant::query();
        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('contact_phone', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function create(array $data): Merchant { return Merchant::create($data); }

    public function update(Merchant $m, array $data): void { $m->update($data); }

    public function updateStatus(Merchant $m, string $status): void { $m->update(['status' => $status]); }

    public function delete(Merchant $m): void { $m->delete(); }
}
```

- [ ] **Step 4.4: FormRequest 三件**

```php
<?php
// backend/app/Http/Requests/Api/Mall/Merchant/StoreMerchantRequest.php
namespace App\Http\Requests\Api\Mall\Merchant;

use App\Http\Requests\Api\ApiFormRequest;

class StoreMerchantRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:80',
            'logo'            => 'nullable|string|max:255',
            'contact_name'    => 'nullable|string|max:50',
            'contact_phone'   => 'nullable|string|max:20',
            'status'          => 'nullable|in:pending,active,disabled',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'remark'          => 'nullable|string|max:255',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Merchant/UpdateMerchantRequest.php
namespace App\Http\Requests\Api\Mall\Merchant;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateMerchantRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'            => 'sometimes|required|string|max:80',
            'logo'            => 'nullable|string|max:255',
            'contact_name'    => 'nullable|string|max:50',
            'contact_phone'   => 'nullable|string|max:20',
            'status'          => 'nullable|in:pending,active,disabled',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'remark'          => 'nullable|string|max:255',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Merchant/UpdateMerchantStatusRequest.php
namespace App\Http\Requests\Api\Mall\Merchant;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateMerchantStatusRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['status' => 'required|in:pending,active,disabled'];
    }
}
```

- [ ] **Step 4.5: Resource**

```php
<?php
// backend/app/Http/Resources/Api/Mall/MerchantResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'logo'            => $this->logo,
            'contact_name'    => $this->contact_name,
            'contact_phone'   => $this->contact_phone,
            'status'          => $this->status,
            'commission_rate' => $this->commission_rate,
            'remark'          => $this->remark,
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
```

- [ ] **Step 4.6: Controller**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/MerchantController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Merchant\StoreMerchantRequest;
use App\Http\Requests\Api\Mall\Merchant\UpdateMerchantRequest;
use App\Http\Requests\Api\Mall\Merchant\UpdateMerchantStatusRequest;
use App\Http\Resources\Api\Mall\MerchantResource;
use App\Models\Mall\Merchant;
use App\Services\Api\Mall\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function __construct(private readonly MerchantService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['keywords', 'status']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, MerchantResource::class);
    }

    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $m = $this->service->create($request->validated());
        return $this->success(new MerchantResource($m), 'api.created');
    }

    public function show(Merchant $merchant): JsonResponse
    {
        return $this->success(new MerchantResource($merchant));
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): JsonResponse
    {
        $this->service->update($merchant, $request->validated());
        return $this->success(null, 'api.updated');
    }

    public function destroy(Merchant $merchant): JsonResponse
    {
        $this->service->delete($merchant);
        return $this->success(null, 'api.deleted');
    }

    public function updateStatus(UpdateMerchantStatusRequest $request, Merchant $merchant): JsonResponse
    {
        $this->service->updateStatus($merchant, $request->input('status'));
        return $this->success(null, 'api.status_updated');
    }
}
```

- [ ] **Step 4.7: 临时加路由**

```php
// 在 mall prefix 组内追加
Route::apiResource('merchants', \App\Http\Controllers\Api\Mall\MerchantController::class);
Route::patch('merchants/{merchant}/status', [\App\Http\Controllers\Api\Mall\MerchantController::class, 'updateStatus']);
```

- [ ] **Step 4.8: 运行测试确认通过**

```bash
cd backend && php artisan test --filter=MerchantTest
```

Expected: PASS 5 个测试。

- [ ] **Step 4.9: 提交**

```bash
git add backend/app/Services/Api/Mall/MerchantService.php \
  backend/app/Http/Requests/Api/Mall/Merchant \
  backend/app/Http/Resources/Api/Mall/MerchantResource.php \
  backend/app/Http/Controllers/Api/Mall/MerchantController.php \
  backend/tests/Feature/Mall/MerchantTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增商家管理后端（含测试）"
```

---

## Task 5: 商品管理后端全栈（含 SKU）

**Files:**
- Create: `backend/app/Services/Api/Mall/ProductService.php`
- Create: `backend/app/Http/Requests/Api/Mall/Product/StoreProductRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Product/UpdateProductRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Product/UpdateProductStatusRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Product/StoreSkuRequest.php`
- Create: `backend/app/Http/Resources/Api/Mall/ProductResource.php`
- Create: `backend/app/Http/Resources/Api/Mall/ProductSkuResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/ProductController.php`
- Create: `backend/app/Http/Controllers/Api/Mall/ProductSkuController.php`
- Create: `backend/tests/Feature/Mall/ProductTest.php`

- [ ] **Step 5.1: 编写失败测试**

```php
<?php
// backend/tests/Feature/Mall/ProductTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Product;
use App\Models\Mall\ProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $this->merchant = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
    }

    public function test_index_returns_paginated_products(): void
    {
        Product::create(['tenant_id' => 1, 'merchant_id' => $this->merchant->id, 'name' => 'P1', 'type' => 'physical', 'status' => 'active']);
        $this->getJson('/api/v1/mall/products')->assertOk()
            ->assertJsonStructure(['data' => ['list', 'total']]);
    }

    public function test_index_filters_by_type(): void
    {
        Product::create(['tenant_id' => 1, 'merchant_id' => $this->merchant->id, 'name' => 'A', 'type' => 'physical', 'status' => 'active']);
        Product::create(['tenant_id' => 1, 'merchant_id' => $this->merchant->id, 'name' => 'B', 'type' => 'virtual', 'status' => 'active']);
        $response = $this->getJson('/api/v1/mall/products?type=virtual');
        $this->assertCount(1, $response->json('data.list'));
    }

    public function test_store_creates_product(): void
    {
        $response = $this->postJson('/api/v1/mall/products', [
            'merchant_id' => $this->merchant->id, 'category_id' => 0,
            'name' => '新商品', 'type' => 'physical', 'status' => 'draft',
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('mall_products', ['name' => '新商品']);
    }

    public function test_update_status(): void
    {
        $p = Product::create(['tenant_id' => 1, 'merchant_id' => $this->merchant->id, 'name' => 'P', 'type' => 'physical', 'status' => 'pending']);
        $this->patchJson("/api/v1/mall/products/{$p->id}/status", ['status' => 'active'])->assertOk();
        $this->assertDatabaseHas('mall_products', ['id' => $p->id, 'status' => 'active']);
    }

    public function test_sku_lifecycle(): void
    {
        $p = Product::create(['tenant_id' => 1, 'merchant_id' => $this->merchant->id, 'name' => 'P', 'type' => 'physical', 'status' => 'active']);

        $create = $this->postJson("/api/v1/mall/products/{$p->id}/skus", [
            'spec_values' => ['色' => '红'], 'price' => 9.9, 'stock' => 10, 'code' => 'SKU-01',
        ]);
        $create->assertOk();

        $sku = ProductSku::first();
        $this->putJson("/api/v1/mall/skus/{$sku->id}", ['price' => 19.9])->assertOk();
        $this->deleteJson("/api/v1/mall/skus/{$sku->id}")->assertOk();
        $this->assertDatabaseMissing('mall_product_skus', ['id' => $sku->id]);
    }
}
```

- [ ] **Step 5.2: Service**

```php
<?php
// backend/app/Services/Api/Mall/ProductService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\ProductSku;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Product::with(['merchant', 'category']);
        if (!empty($filters['keywords'])) {
            $query->where('name', 'like', "%{$filters['keywords']}%");
        }
        if (!empty($filters['type']))         $query->where('type', $filters['type']);
        if (!empty($filters['status']))       $query->where('status', $filters['status']);
        if (!empty($filters['merchant_id']))  $query->where('merchant_id', $filters['merchant_id']);
        if (!empty($filters['category_id']))  $query->where('category_id', $filters['category_id']);

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function create(array $data, array $skus = []): Product
    {
        return DB::transaction(function () use ($data, $skus) {
            $product = Product::create($data);
            foreach ($skus as $sku) {
                $sku['product_id'] = $product->id;
                ProductSku::create($sku);
            }
            return $product;
        });
    }

    public function update(Product $p, array $data): void { $p->update($data); }

    public function updateStatus(Product $p, string $status): void { $p->update(['status' => $status]); }

    public function delete(Product $p): void
    {
        DB::transaction(function () use ($p) {
            ProductSku::where('product_id', $p->id)->delete();
            $p->delete();
        });
    }

    public function createSku(Product $p, array $data): ProductSku
    {
        $data['product_id'] = $p->id;
        return ProductSku::create($data);
    }

    public function updateSku(ProductSku $sku, array $data): void { $sku->update($data); }

    public function deleteSku(ProductSku $sku): void { $sku->delete(); }
}
```

- [ ] **Step 5.3: FormRequests**

```php
<?php
// backend/app/Http/Requests/Api/Mall/Product/StoreProductRequest.php
namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class StoreProductRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'merchant_id' => 'required|integer|exists:mall_merchants,id',
            'category_id' => 'nullable|integer|min:0',
            'name'        => 'required|string|max:120',
            'type'        => 'required|in:physical,virtual,service',
            'cover'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,pending,active,off_shelf',
            'sort'        => 'nullable|integer|min:0',
            'skus'        => 'nullable|array',
            'skus.*.spec_values' => 'nullable|array',
            'skus.*.price'       => 'required_with:skus|numeric|min:0',
            'skus.*.stock'       => 'required_with:skus|integer',
            'skus.*.code'        => 'nullable|string|max:50',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Product/UpdateProductRequest.php
namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateProductRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'merchant_id' => 'sometimes|integer|exists:mall_merchants,id',
            'category_id' => 'nullable|integer|min:0',
            'name'        => 'sometimes|required|string|max:120',
            'type'        => 'sometimes|in:physical,virtual,service',
            'cover'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,pending,active,off_shelf',
            'sort'        => 'nullable|integer|min:0',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Product/UpdateProductStatusRequest.php
namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateProductStatusRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['status' => 'required|in:draft,pending,active,off_shelf'];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Product/StoreSkuRequest.php
namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class StoreSkuRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'spec_values' => 'nullable|array',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer',
            'code'        => 'nullable|string|max:50',
        ];
    }
}
```

- [ ] **Step 5.4: Resources**

```php
<?php
// backend/app/Http/Resources/Api/Mall/ProductResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'merchant_id' => $this->merchant_id,
            'category_id' => $this->category_id,
            'name'        => $this->name,
            'type'        => $this->type,
            'cover'       => $this->cover,
            'description' => $this->description,
            'status'      => $this->status,
            'sort'        => $this->sort,
            'created_at'  => $this->created_at?->toDateTimeString(),
            'merchant'    => $this->whenLoaded('merchant', fn() => ['id' => $this->merchant->id, 'name' => $this->merchant->name]),
            'category'    => $this->whenLoaded('category', fn() => ['id' => $this->category->id, 'name' => $this->category->name]),
            'skus'        => $this->whenLoaded('skus', fn() => ProductSkuResource::collection($this->skus)->resolve()),
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Api/Mall/ProductSkuResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSkuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'spec_values' => $this->spec_values,
            'price'       => $this->price,
            'stock'       => $this->stock,
            'code'        => $this->code,
        ];
    }
}
```

- [ ] **Step 5.5: Controllers**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/ProductController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Product\StoreProductRequest;
use App\Http\Requests\Api\Mall\Product\UpdateProductRequest;
use App\Http\Requests\Api\Mall\Product\UpdateProductStatusRequest;
use App\Http\Resources\Api\Mall\ProductResource;
use App\Models\Mall\Product;
use App\Services\Api\Mall\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['keywords', 'type', 'status', 'merchant_id', 'category_id']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $skus = $data['skus'] ?? [];
        unset($data['skus']);
        $product = $this->service->create($data, $skus);
        return $this->success(new ProductResource($product), 'api.created');
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['merchant', 'category', 'skus']);
        return $this->success(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->service->update($product, $request->validated());
        return $this->success(null, 'api.updated');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);
        return $this->success(null, 'api.deleted');
    }

    public function updateStatus(UpdateProductStatusRequest $request, Product $product): JsonResponse
    {
        $this->service->updateStatus($product, $request->input('status'));
        return $this->success(null, 'api.status_updated');
    }
}
```

```php
<?php
// backend/app/Http/Controllers/Api/Mall/ProductSkuController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Product\StoreSkuRequest;
use App\Http\Resources\Api\Mall\ProductSkuResource;
use App\Models\Mall\Product;
use App\Models\Mall\ProductSku;
use App\Services\Api\Mall\ProductService;
use Illuminate\Http\JsonResponse;

class ProductSkuController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Product $product): JsonResponse
    {
        return $this->success(ProductSkuResource::collection($product->skus)->resolve());
    }

    public function store(StoreSkuRequest $request, Product $product): JsonResponse
    {
        $sku = $this->service->createSku($product, $request->validated());
        return $this->success(new ProductSkuResource($sku), 'api.created');
    }

    public function update(StoreSkuRequest $request, ProductSku $sku): JsonResponse
    {
        $this->service->updateSku($sku, $request->validated());
        return $this->success(null, 'api.updated');
    }

    public function destroy(ProductSku $sku): JsonResponse
    {
        $this->service->deleteSku($sku);
        return $this->success(null, 'api.deleted');
    }
}
```

- [ ] **Step 5.6: 临时加路由**

```php
Route::apiResource('products', \App\Http\Controllers\Api\Mall\ProductController::class);
Route::patch('products/{product}/status', [\App\Http\Controllers\Api\Mall\ProductController::class, 'updateStatus']);
// 嵌套 shallow：父资源 {product}/skus 仅 index/store；子资源 skus/{sku} 独立 update/destroy
Route::apiResource('products.skus', \App\Http\Controllers\Api\Mall\ProductSkuController::class)->shallow();
```

- [ ] **Step 5.7: 运行测试**

```bash
cd backend && php artisan test --filter=ProductTest
```

Expected: PASS 5 个测试。

- [ ] **Step 5.8: 提交**

```bash
git add backend/app/Services/Api/Mall/ProductService.php \
  backend/app/Http/Requests/Api/Mall/Product \
  backend/app/Http/Resources/Api/Mall/ProductResource.php \
  backend/app/Http/Resources/Api/Mall/ProductSkuResource.php \
  backend/app/Http/Controllers/Api/Mall/ProductController.php \
  backend/app/Http/Controllers/Api/Mall/ProductSkuController.php \
  backend/tests/Feature/Mall/ProductTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增商品与 SKU 后端（含测试）"
```

---

## Task 6: 订单管理后端全栈

**Files:**
- Create: `backend/app/Services/Api/Mall/OrderService.php`
- Create: `backend/app/Http/Resources/Api/Mall/OrderResource.php`
- Create: `backend/app/Http/Resources/Api/Mall/OrderItemResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/OrderController.php`
- Create: `backend/tests/Feature/Mall/OrderTest.php`

- [ ] **Step 6.1: 编写失败测试**

```php
<?php
// backend/tests/Feature/Mall/OrderTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $this->merchant = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
    }

    private function createOrder(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'tenant_id'      => 1,
            'merchant_id'    => $this->merchant->id,
            'order_no'       => 'ORD' . uniqid(),
            'buyer_name'     => 'Alice',
            'buyer_phone'    => '13800000000',
            'total_amount'   => 100,
            'pay_amount'     => 100,
            'status'         => 'pending',
            'payment_method' => 'wechat',
        ], $attrs));
    }

    public function test_index_returns_paginated_orders(): void
    {
        $this->createOrder();
        $this->getJson('/api/v1/mall/orders')->assertOk()
            ->assertJsonStructure(['data' => ['list', 'total']]);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createOrder(['status' => 'paid']);
        $this->createOrder(['status' => 'cancelled']);
        $response = $this->getJson('/api/v1/mall/orders?status=paid');
        $this->assertCount(1, $response->json('data.list'));
    }

    public function test_show_returns_order_with_items(): void
    {
        $order = $this->createOrder();
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => 1, 'product_name' => 'X',
            'price' => 10, 'quantity' => 2, 'subtotal' => 20,
        ]);

        $response = $this->getJson("/api/v1/mall/orders/{$order->id}");
        $response->assertOk()->assertJsonPath('data.items.0.product_name', 'X');
    }

    public function test_cancel_order(): void
    {
        $order = $this->createOrder(['status' => 'pending']);

        $response = $this->patchJson("/api/v1/mall/orders/{$order->id}/cancel");
        $response->assertOk();
        $this->assertDatabaseHas('mall_orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_cancel_paid_order_rejected(): void
    {
        $order = $this->createOrder(['status' => 'paid']);

        $response = $this->patchJson("/api/v1/mall/orders/{$order->id}/cancel");
        $response->assertStatus(400);
    }
}
```

- [ ] **Step 6.2: Service**

```php
<?php
// backend/app/Services/Api/Mall/OrderService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Order;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Order::with(['merchant']);

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                  ->orWhere('buyer_name', 'like', "%{$kw}%")
                  ->orWhere('buyer_phone', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['status']))         $query->where('status', $filters['status']);
        if (!empty($filters['payment_method'])) $query->where('payment_method', $filters['payment_method']);
        if (!empty($filters['merchant_id']))    $query->where('merchant_id', $filters['merchant_id']);
        if (!empty($filters['start_date']))     $query->where('created_at', '>=', $filters['start_date']);
        if (!empty($filters['end_date']))       $query->where('created_at', '<=', $filters['end_date']);

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function detail(Order $order): Order
    {
        return $order->load(['merchant', 'items', 'payments', 'refunds', 'delivery']);
    }

    public function cancel(Order $order): void
    {
        if ($order->status !== 'pending') {
            abort(400, '仅待支付订单可取消');
        }
        $order->update(['status' => 'cancelled']);
    }

    public function update(Order $order, array $data): void
    {
        $order->update($data);
    }
}
```

- [ ] **Step 6.3: Resources**

```php
<?php
// backend/app/Http/Resources/Api/Mall/OrderItemResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'sku_id'       => $this->sku_id,
            'product_name' => $this->product_name,
            'spec_values'  => $this->spec_values,
            'price'        => $this->price,
            'quantity'     => $this->quantity,
            'subtotal'     => $this->subtotal,
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Api/Mall/OrderResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'order_no'       => $this->order_no,
            'merchant_id'    => $this->merchant_id,
            'buyer_name'     => $this->buyer_name,
            'buyer_phone'    => $this->buyer_phone,
            'total_amount'   => $this->total_amount,
            'pay_amount'     => $this->pay_amount,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'paid_at'        => $this->paid_at?->toDateTimeString(),
            'remark'         => $this->remark,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'merchant'       => $this->whenLoaded('merchant', fn() => ['id' => $this->merchant->id, 'name' => $this->merchant->name]),
            'items'          => $this->whenLoaded('items', fn() => OrderItemResource::collection($this->items)->resolve()),
            'payments'       => $this->whenLoaded('payments', fn() => PaymentResource::collection($this->payments)->resolve()),
            'delivery'       => $this->whenLoaded('delivery', fn() => $this->delivery ? (new DeliveryResource($this->delivery))->resolve() : null),
        ];
    }
}
```

- [ ] **Step 6.4: Controller**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/OrderController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\Mall\OrderResource;
use App\Models\Mall\Order;
use App\Services\Api\Mall\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['keywords', 'status', 'payment_method', 'merchant_id', 'start_date', 'end_date']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, OrderResource::class);
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success(new OrderResource($this->service->detail($order)));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $this->service->update($order, $request->only(['remark']));
        return $this->success(null, 'api.updated');
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->service->cancel($order);
        return $this->success(null, 'api.updated');
    }
}
```

- [ ] **Step 6.5: 临时加路由**

```php
Route::apiResource('orders', \App\Http\Controllers\Api\Mall\OrderController::class)->except(['store', 'destroy']);
Route::patch('orders/{order}/cancel', [\App\Http\Controllers\Api\Mall\OrderController::class, 'cancel']);
```

- [ ] **Step 6.6: 运行测试**

```bash
cd backend && php artisan test --filter=OrderTest
```

Expected: PASS 5 个测试。

- [ ] **Step 6.7: 提交**

```bash
git add backend/app/Services/Api/Mall/OrderService.php \
  backend/app/Http/Resources/Api/Mall/OrderResource.php \
  backend/app/Http/Resources/Api/Mall/OrderItemResource.php \
  backend/app/Http/Controllers/Api/Mall/OrderController.php \
  backend/tests/Feature/Mall/OrderTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增订单管理后端（含测试）"
```

---

## Task 7: 退款售后后端

**Files:**
- Create: `backend/app/Services/Api/Mall/RefundService.php`
- Create: `backend/app/Http/Requests/Api/Mall/Refund/RejectRefundRequest.php`
- Create: `backend/app/Http/Resources/Api/Mall/RefundResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/RefundController.php`
- Create: `backend/tests/Feature/Mall/RefundTest.php`

- [ ] **Step 7.1: 测试**

```php
<?php
// backend/tests/Feature/Mall/RefundTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use App\Models\Mall\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
        $this->order = Order::create([
            'tenant_id' => 1, 'merchant_id' => $m->id, 'order_no' => 'O1',
            'total_amount' => 100, 'pay_amount' => 100, 'status' => 'paid',
        ]);
    }

    public function test_approve_refund(): void
    {
        $refund = Refund::create(['order_id' => $this->order->id, 'amount' => 50, 'reason' => '退货', 'status' => 'pending']);

        $response = $this->patchJson("/api/v1/mall/refunds/{$refund->id}/approve");
        $response->assertOk();
        $this->assertDatabaseHas('mall_refunds', ['id' => $refund->id, 'status' => 'approved']);
    }

    public function test_reject_refund(): void
    {
        $refund = Refund::create(['order_id' => $this->order->id, 'amount' => 50, 'reason' => 'x', 'status' => 'pending']);

        $response = $this->patchJson("/api/v1/mall/refunds/{$refund->id}/reject", ['reject_reason' => '不符合']);
        $response->assertOk();
        $this->assertDatabaseHas('mall_refunds', ['id' => $refund->id, 'status' => 'rejected', 'reject_reason' => '不符合']);
    }

    public function test_approve_already_processed_rejected(): void
    {
        $refund = Refund::create(['order_id' => $this->order->id, 'amount' => 50, 'status' => 'approved']);

        $response = $this->patchJson("/api/v1/mall/refunds/{$refund->id}/approve");
        $response->assertStatus(400);
    }
}
```

- [ ] **Step 7.2: Service**

```php
<?php
// backend/app/Services/Api/Mall/RefundService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Refund;
use Illuminate\Pagination\LengthAwarePaginator;

class RefundService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Refund::query()->with('order');
        if (!empty($filters['status']))   $query->where('status', $filters['status']);
        if (!empty($filters['order_no'])) {
            $query->whereHas('order', fn($q) => $q->where('order_no', 'like', "%{$filters['order_no']}%"));
        }
        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function approve(Refund $refund): void
    {
        if ($refund->status !== 'pending') abort(400, '该退款单已处理');
        $refund->update(['status' => 'approved']);
    }

    public function reject(Refund $refund, string $reason): void
    {
        if ($refund->status !== 'pending') abort(400, '该退款单已处理');
        $refund->update(['status' => 'rejected', 'reject_reason' => $reason]);
    }

    public function update(Refund $refund, array $data): void { $refund->update($data); }
}
```

- [ ] **Step 7.3: Request + Resource + Controller**

```php
<?php
// backend/app/Http/Requests/Api/Mall/Refund/RejectRefundRequest.php
namespace App\Http\Requests\Api\Mall\Refund;

use App\Http\Requests\Api\ApiFormRequest;

class RejectRefundRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['reject_reason' => 'required|string|max:255'];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Api/Mall/RefundResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'order_id'      => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'amount'        => $this->amount,
            'reason'        => $this->reason,
            'status'        => $this->status,
            'reject_reason' => $this->reject_reason,
            'created_at'    => $this->created_at?->toDateTimeString(),
            'order'         => $this->whenLoaded('order', fn() => ['id' => $this->order->id, 'order_no' => $this->order->order_no]),
        ];
    }
}
```

```php
<?php
// backend/app/Http/Controllers/Api/Mall/RefundController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Refund\RejectRefundRequest;
use App\Http\Resources\Api\Mall\RefundResource;
use App\Models\Mall\Refund;
use App\Services\Api\Mall\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['status', 'order_no']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, RefundResource::class);
    }

    public function show(Refund $refund): JsonResponse
    {
        $refund->load('order');
        return $this->success(new RefundResource($refund));
    }

    public function update(Request $request, Refund $refund): JsonResponse
    {
        $this->service->update($refund, $request->only(['reason', 'amount']));
        return $this->success(null, 'api.updated');
    }

    public function destroy(Refund $refund): JsonResponse
    {
        $refund->delete();
        return $this->success(null, 'api.deleted');
    }

    public function approve(Refund $refund): JsonResponse
    {
        $this->service->approve($refund);
        return $this->success(null, 'api.status_updated');
    }

    public function reject(RejectRefundRequest $request, Refund $refund): JsonResponse
    {
        $this->service->reject($refund, $request->input('reject_reason'));
        return $this->success(null, 'api.status_updated');
    }
}
```

- [ ] **Step 7.4: 临时加路由**

```php
Route::apiResource('refunds', \App\Http\Controllers\Api\Mall\RefundController::class)->except(['store']);
Route::patch('refunds/{refund}/approve', [\App\Http\Controllers\Api\Mall\RefundController::class, 'approve']);
Route::patch('refunds/{refund}/reject', [\App\Http\Controllers\Api\Mall\RefundController::class, 'reject']);
```

- [ ] **Step 7.5: 运行测试 + 提交**

```bash
cd backend && php artisan test --filter=RefundTest
git add backend/app/Services/Api/Mall/RefundService.php \
  backend/app/Http/Requests/Api/Mall/Refund \
  backend/app/Http/Resources/Api/Mall/RefundResource.php \
  backend/app/Http/Controllers/Api/Mall/RefundController.php \
  backend/tests/Feature/Mall/RefundTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增退款售后后端（含测试）"
```

---

## Task 8: 支付记录后端

**Files:**
- Create: `backend/app/Http/Resources/Api/Mall/PaymentResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/PaymentController.php`
- Create: `backend/tests/Feature/Mall/PaymentTest.php`

- [ ] **Step 8.1: 测试**

```php
<?php
// backend/tests/Feature/Mall/PaymentTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use App\Models\Mall\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    public function test_index_returns_payments(): void
    {
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
        $o = Order::create(['tenant_id' => 1, 'merchant_id' => $m->id, 'order_no' => 'O1', 'total_amount' => 10, 'pay_amount' => 10, 'status' => 'paid']);
        Payment::create(['order_id' => $o->id, 'method' => 'wechat', 'amount' => 10, 'trade_no' => 'T1', 'status' => 'success']);

        $response = $this->getJson('/api/v1/mall/payments');
        $response->assertOk()->assertJsonStructure(['data' => ['list', 'total']]);
    }

    public function test_index_filters_by_method(): void
    {
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
        $o = Order::create(['tenant_id' => 1, 'merchant_id' => $m->id, 'order_no' => 'O2', 'total_amount' => 10, 'pay_amount' => 10, 'status' => 'paid']);
        Payment::create(['order_id' => $o->id, 'method' => 'wechat', 'amount' => 10, 'status' => 'success']);
        Payment::create(['order_id' => $o->id, 'method' => 'alipay', 'amount' => 10, 'status' => 'success']);

        $response = $this->getJson('/api/v1/mall/payments?method=wechat');
        $this->assertCount(1, $response->json('data.list'));
    }
}
```

- [ ] **Step 8.2: Resource + Controller**

```php
<?php
// backend/app/Http/Resources/Api/Mall/PaymentResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'order_id'   => $this->order_id,
            'method'     => $this->method,
            'amount'     => $this->amount,
            'trade_no'   => $this->trade_no,
            'status'     => $this->status,
            'paid_at'    => $this->paid_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'order'      => $this->whenLoaded('order', fn() => ['id' => $this->order->id, 'order_no' => $this->order->order_no]),
        ];
    }
}
```

```php
<?php
// backend/app/Http/Controllers/Api/Mall/PaymentController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\Mall\PaymentResource;
use App\Models\Mall\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('order');

        if ($request->filled('method'))   $query->where('method', $request->input('method'));
        if ($request->filled('status'))   $query->where('status', $request->input('status'));
        if ($request->filled('order_no')) {
            $on = $request->input('order_no');
            $query->whereHas('order', fn($q) => $q->where('order_no', 'like', "%{$on}%"));
        }
        if ($request->filled('start_date')) $query->where('paid_at', '>=', $request->input('start_date'));
        if ($request->filled('end_date'))   $query->where('paid_at', '<=', $request->input('end_date'));

        $paginator = $query->orderByDesc('id')->paginate((int) $request->input('pageSize', 10));
        return $this->paginate($paginator, PaymentResource::class);
    }
}
```

- [ ] **Step 8.3: 临时加路由 + 测试 + 提交**

```php
Route::get('payments', [\App\Http\Controllers\Api\Mall\PaymentController::class, 'index']);
```

```bash
cd backend && php artisan test --filter=PaymentTest
git add backend/app/Http/Resources/Api/Mall/PaymentResource.php \
  backend/app/Http/Controllers/Api/Mall/PaymentController.php \
  backend/tests/Feature/Mall/PaymentTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增支付记录后端（含测试）"
```

---

## Task 9: 配送管理后端全栈

**Files:**
- Create: `backend/app/Services/Api/Mall/DeliveryService.php`
- Create: `backend/app/Services/Api/Mall/DeliveryStaffService.php`
- Create: `backend/app/Http/Requests/Api/Mall/Delivery/AssignDeliveryRequest.php`
- Create: `backend/app/Http/Requests/Api/Mall/Delivery/StoreDeliveryStaffRequest.php`
- Create: `backend/app/Http/Resources/Api/Mall/DeliveryResource.php`
- Create: `backend/app/Http/Resources/Api/Mall/DeliveryStaffResource.php`
- Create: `backend/app/Http/Controllers/Api/Mall/DeliveryController.php`
- Create: `backend/app/Http/Controllers/Api/Mall/DeliveryStaffController.php`
- Create: `backend/tests/Feature/Mall/DeliveryTest.php`

- [ ] **Step 9.1: 测试**

```php
<?php
// backend/tests/Feature/Mall/DeliveryTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Delivery;
use App\Models\Mall\DeliveryStaff;
use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
        $this->order = Order::create([
            'tenant_id' => 1, 'merchant_id' => $m->id, 'order_no' => 'O1',
            'total_amount' => 10, 'pay_amount' => 10, 'status' => 'paid',
        ]);
    }

    public function test_delivery_staff_crud(): void
    {
        $this->postJson('/api/v1/mall/delivery-staff', ['name' => '小李', 'phone' => '13800000000', 'status' => 1])
            ->assertOk();
        $this->assertDatabaseHas('mall_delivery_staff', ['name' => '小李']);

        $staff = DeliveryStaff::first();
        $this->putJson("/api/v1/mall/delivery-staff/{$staff->id}", ['status' => 0])->assertOk();
        $this->deleteJson("/api/v1/mall/delivery-staff/{$staff->id}")->assertOk();
    }

    public function test_assign_self_delivery(): void
    {
        $delivery = Delivery::create(['order_id' => $this->order->id, 'type' => 'self', 'status' => 'pending']);
        $staff = DeliveryStaff::create(['tenant_id' => 1, 'name' => 'Rider', 'phone' => '1', 'status' => 1]);

        $response = $this->patchJson("/api/v1/mall/deliveries/{$delivery->id}/assign", [
            'type' => 'self', 'staff_id' => $staff->id,
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('mall_deliveries', ['id' => $delivery->id, 'staff_id' => $staff->id, 'status' => 'assigned']);
    }

    public function test_assign_express_delivery(): void
    {
        $delivery = Delivery::create(['order_id' => $this->order->id, 'type' => 'express', 'status' => 'pending']);

        $response = $this->patchJson("/api/v1/mall/deliveries/{$delivery->id}/assign", [
            'type' => 'express', 'express_company' => '顺丰', 'tracking_no' => 'SF123',
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('mall_deliveries', ['id' => $delivery->id, 'tracking_no' => 'SF123', 'status' => 'shipping']);
    }
}
```

- [ ] **Step 9.2: Services**

```php
<?php
// backend/app/Services/Api/Mall/DeliveryService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Delivery;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Delivery::query()->with(['order', 'staff']);
        if (!empty($filters['type']))   $query->where('type', $filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function assign(Delivery $d, array $data): void
    {
        if ($d->status === 'done') abort(400, '已完成的配送单不可修改');

        if ($data['type'] === 'self') {
            $d->update([
                'type'        => 'self',
                'staff_id'    => $data['staff_id'],
                'status'      => 'assigned',
                'shipped_at'  => now(),
            ]);
        } else {
            $d->update([
                'type'            => 'express',
                'express_company' => $data['express_company'],
                'tracking_no'     => $data['tracking_no'],
                'status'          => 'shipping',
                'shipped_at'      => now(),
            ]);
        }
    }

    public function update(Delivery $d, array $data): void { $d->update($data); }
}
```

```php
<?php
// backend/app/Services/Api/Mall/DeliveryStaffService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\DeliveryStaff;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryStaffService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = DeliveryStaff::query();
        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('phone', 'like', "%{$kw}%");
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function create(array $data): DeliveryStaff { return DeliveryStaff::create($data); }
    public function update(DeliveryStaff $s, array $data): void { $s->update($data); }
    public function delete(DeliveryStaff $s): void { $s->delete(); }
}
```

- [ ] **Step 9.3: FormRequests**

```php
<?php
// backend/app/Http/Requests/Api/Mall/Delivery/AssignDeliveryRequest.php
namespace App\Http\Requests\Api\Mall\Delivery;

use App\Http\Requests\Api\ApiFormRequest;

class AssignDeliveryRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'type'            => 'required|in:self,express',
            'staff_id'        => 'required_if:type,self|integer|exists:mall_delivery_staff,id',
            'express_company' => 'required_if:type,express|string|max:50',
            'tracking_no'     => 'required_if:type,express|string|max:64',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Api/Mall/Delivery/StoreDeliveryStaffRequest.php
namespace App\Http\Requests\Api\Mall\Delivery;

use App\Http\Requests\Api\ApiFormRequest;

class StoreDeliveryStaffRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:50',
            'phone'  => 'required|string|max:20',
            'status' => 'nullable|in:0,1',
        ];
    }
}
```

- [ ] **Step 9.4: Resources**

```php
<?php
// backend/app/Http/Resources/Api/Mall/DeliveryResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'order_id'        => $this->order_id,
            'type'            => $this->type,
            'staff_id'        => $this->staff_id,
            'express_company' => $this->express_company,
            'tracking_no'     => $this->tracking_no,
            'status'          => $this->status,
            'shipped_at'      => $this->shipped_at?->toDateTimeString(),
            'completed_at'    => $this->completed_at?->toDateTimeString(),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'order'           => $this->whenLoaded('order', fn() => ['id' => $this->order->id, 'order_no' => $this->order->order_no, 'buyer_name' => $this->order->buyer_name]),
            'staff'           => $this->whenLoaded('staff', fn() => $this->staff ? ['id' => $this->staff->id, 'name' => $this->staff->name, 'phone' => $this->staff->phone] : null),
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Api/Mall/DeliveryStaffResource.php
namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryStaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'phone'      => $this->phone,
            'status'     => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
```

- [ ] **Step 9.5: Controllers**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/DeliveryController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Delivery\AssignDeliveryRequest;
use App\Http\Resources\Api\Mall\DeliveryResource;
use App\Models\Mall\Delivery;
use App\Services\Api\Mall\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['type', 'status']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, DeliveryResource::class);
    }

    public function show(Delivery $delivery): JsonResponse
    {
        $delivery->load(['order', 'staff']);
        return $this->success(new DeliveryResource($delivery));
    }

    public function update(Request $request, Delivery $delivery): JsonResponse
    {
        $this->service->update($delivery, $request->only(['status', 'completed_at']));
        return $this->success(null, 'api.updated');
    }

    public function assign(AssignDeliveryRequest $request, Delivery $delivery): JsonResponse
    {
        $this->service->assign($delivery, $request->validated());
        return $this->success(null, 'api.updated');
    }
}
```

```php
<?php
// backend/app/Http/Controllers/Api/Mall/DeliveryStaffController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Delivery\StoreDeliveryStaffRequest;
use App\Http\Resources\Api\Mall\DeliveryStaffResource;
use App\Models\Mall\DeliveryStaff;
use App\Services\Api\Mall\DeliveryStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryStaffController extends Controller
{
    public function __construct(private readonly DeliveryStaffService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['keywords', 'status']),
            (int) $request->input('pageSize', 10)
        );
        return $this->paginate($paginator, DeliveryStaffResource::class);
    }

    public function store(StoreDeliveryStaffRequest $request): JsonResponse
    {
        $staff = $this->service->create($request->validated());
        return $this->success(new DeliveryStaffResource($staff), 'api.created');
    }

    public function show(DeliveryStaff $deliveryStaff): JsonResponse
    {
        return $this->success(new DeliveryStaffResource($deliveryStaff));
    }

    public function update(StoreDeliveryStaffRequest $request, DeliveryStaff $deliveryStaff): JsonResponse
    {
        $this->service->update($deliveryStaff, $request->validated());
        return $this->success(null, 'api.updated');
    }

    public function destroy(DeliveryStaff $deliveryStaff): JsonResponse
    {
        $this->service->delete($deliveryStaff);
        return $this->success(null, 'api.deleted');
    }
}
```

- [ ] **Step 9.6: 临时加路由**

```php
Route::apiResource('deliveries', \App\Http\Controllers\Api\Mall\DeliveryController::class)->except(['store', 'destroy']);
Route::patch('deliveries/{delivery}/assign', [\App\Http\Controllers\Api\Mall\DeliveryController::class, 'assign']);
Route::apiResource('delivery-staff', \App\Http\Controllers\Api\Mall\DeliveryStaffController::class);
```

注：Laravel route-model binding 会使用 `{delivery_staff}` 参数名。控制器方法签名需与之匹配；若路径参数 `{delivery_staff}` 写法不便，保持控制器方法参数名 `DeliveryStaff $deliveryStaff` 即可（camelCase 自动映射）。

- [ ] **Step 9.7: 运行测试 + 提交**

```bash
cd backend && php artisan test --filter=DeliveryTest
git add backend/app/Services/Api/Mall/DeliveryService.php \
  backend/app/Services/Api/Mall/DeliveryStaffService.php \
  backend/app/Http/Requests/Api/Mall/Delivery \
  backend/app/Http/Resources/Api/Mall/DeliveryResource.php \
  backend/app/Http/Resources/Api/Mall/DeliveryStaffResource.php \
  backend/app/Http/Controllers/Api/Mall/DeliveryController.php \
  backend/app/Http/Controllers/Api/Mall/DeliveryStaffController.php \
  backend/tests/Feature/Mall/DeliveryTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增配送管理与配送员后端（含测试）"
```

---

## Task 10: 概括统计后端

**Files:**
- Create: `backend/app/Services/Api/Mall/OverviewService.php`
- Create: `backend/app/Http/Controllers/Api/Mall/OverviewController.php`
- Create: `backend/tests/Feature/Mall/OverviewTest.php`

- [ ] **Step 10.1: 测试**

```php
<?php
// backend/tests/Feature/Mall/OverviewTest.php
namespace Tests\Feature\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use App\Models\Mall\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    public function test_overview_returns_stats(): void
    {
        $m = Merchant::create(['tenant_id' => 1, 'name' => 'M', 'status' => 'active']);
        Product::create(['tenant_id' => 1, 'merchant_id' => $m->id, 'name' => 'P', 'type' => 'physical', 'status' => 'pending']);
        Order::create([
            'tenant_id' => 1, 'merchant_id' => $m->id, 'order_no' => 'O1',
            'total_amount' => 100, 'pay_amount' => 100, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/mall/overview');
        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['gmv', 'order_count', 'merchant_count', 'pending_product_count', 'trend']]);
    }
}
```

- [ ] **Step 10.2: Service**

```php
<?php
// backend/app/Services/Api/Mall/OverviewService.php
namespace App\Services\Api\Mall;

use App\Models\Mall\Merchant;
use App\Models\Mall\Order;
use App\Models\Mall\Product;
use Illuminate\Support\Carbon;

class OverviewService
{
    public function stats(): array
    {
        $gmv = Order::whereIn('status', ['paid', 'shipped', 'done'])->sum('pay_amount');
        $orderCount = Order::count();
        $merchantCount = Merchant::where('status', 'active')->count();
        $pendingProductCount = Product::where('status', 'pending')->count();

        // 近 7 日订单数 + GMV 趋势
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $next = $date->copy()->addDay();
            $dailyOrders = Order::whereBetween('created_at', [$date, $next])->count();
            $dailyGmv = Order::whereBetween('paid_at', [$date, $next])
                ->whereIn('status', ['paid', 'shipped', 'done'])
                ->sum('pay_amount');
            $trend[] = [
                'date'   => $date->toDateString(),
                'orders' => $dailyOrders,
                'gmv'    => (float) $dailyGmv,
            ];
        }

        return [
            'gmv'                   => (float) $gmv,
            'order_count'           => $orderCount,
            'merchant_count'        => $merchantCount,
            'pending_product_count' => $pendingProductCount,
            'trend'                 => $trend,
        ];
    }
}
```

- [ ] **Step 10.3: Controller**

```php
<?php
// backend/app/Http/Controllers/Api/Mall/OverviewController.php
namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Services\Api\Mall\OverviewService;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __construct(private readonly OverviewService $service) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->stats());
    }
}
```

- [ ] **Step 10.4: 路由 + 测试 + 提交**

```php
Route::get('overview', [\App\Http\Controllers\Api\Mall\OverviewController::class, 'index']);
```

```bash
cd backend && php artisan test --filter=OverviewTest
git add backend/app/Services/Api/Mall/OverviewService.php \
  backend/app/Http/Controllers/Api/Mall/OverviewController.php \
  backend/tests/Feature/Mall/OverviewTest.php \
  backend/routes/api.php
git commit -m "feat(mall): 新增概括统计后端（含测试）"
```

---

## Task 11: 整理注册所有 Mall 路由

**Files:**
- Modify: `backend/routes/api.php`

说明：Task 3~10 各自"临时加路由"时可能散乱或顺序不一致，这个任务做一次整理，确保路由分组和顺序清晰一致。

- [ ] **Step 11.1: 统一整理 mall 路由段**

把 `tenant` 中间件组内原来散落的 mall 路由全部替换为下面这一段，保持顺序一致：

```php
// 商城系统
Route::prefix('mall')->group(function () {
    Route::get('overview', [\App\Http\Controllers\Api\Mall\OverviewController::class, 'index']);

    Route::apiResource('categories', \App\Http\Controllers\Api\Mall\ProductCategoryController::class);

    Route::apiResource('merchants', \App\Http\Controllers\Api\Mall\MerchantController::class);
    Route::patch('merchants/{merchant}/status', [\App\Http\Controllers\Api\Mall\MerchantController::class, 'updateStatus']);

    Route::apiResource('products', \App\Http\Controllers\Api\Mall\ProductController::class);
    Route::patch('products/{product}/status', [\App\Http\Controllers\Api\Mall\ProductController::class, 'updateStatus']);
    Route::apiResource('products.skus', \App\Http\Controllers\Api\Mall\ProductSkuController::class)->shallow();

    Route::apiResource('orders', \App\Http\Controllers\Api\Mall\OrderController::class)->except(['store', 'destroy']);
    Route::patch('orders/{order}/cancel', [\App\Http\Controllers\Api\Mall\OrderController::class, 'cancel']);

    Route::apiResource('refunds', \App\Http\Controllers\Api\Mall\RefundController::class)->except(['store']);
    Route::patch('refunds/{refund}/approve', [\App\Http\Controllers\Api\Mall\RefundController::class, 'approve']);
    Route::patch('refunds/{refund}/reject', [\App\Http\Controllers\Api\Mall\RefundController::class, 'reject']);

    Route::get('payments', [\App\Http\Controllers\Api\Mall\PaymentController::class, 'index']);

    Route::apiResource('deliveries', \App\Http\Controllers\Api\Mall\DeliveryController::class)->except(['store', 'destroy']);
    Route::patch('deliveries/{delivery}/assign', [\App\Http\Controllers\Api\Mall\DeliveryController::class, 'assign']);

    Route::apiResource('delivery-staff', \App\Http\Controllers\Api\Mall\DeliveryStaffController::class);
});
```

- [ ] **Step 11.2: 全量回归测试**

```bash
cd backend && php artisan test --filter=Mall
```

Expected: 全部 8 个测试文件、约 30+ 测试用例 PASS。

- [ ] **Step 11.3: 提交**

```bash
git add backend/routes/api.php
git commit -m "refactor(mall): 整理 mall 路由分组顺序"
```

---

## Task 12: 前端 API 封装

**Files:**
- Modify: `frontend/src/api/category.ts`（更新为 `/mall/categories`）
- Create: `frontend/src/api/mall/merchant.ts`
- Create: `frontend/src/api/mall/product.ts`
- Create: `frontend/src/api/mall/order.ts`
- Create: `frontend/src/api/mall/refund.ts`
- Create: `frontend/src/api/mall/payment.ts`
- Create: `frontend/src/api/mall/delivery.ts`
- Create: `frontend/src/api/mall/deliveryStaff.ts`
- Create: `frontend/src/api/mall/overview.ts`

- [ ] **Step 12.1: 更新现有 category.ts 路径**

把 `frontend/src/api/category.ts` 的 URL 从 `/categories` 改为 `/mall/categories`：

```ts
// frontend/src/api/category.ts
import request from '@/utils/request';

export function getCategoryList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/categories', method: 'get', params });
}

export function getCategoryDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/categories/${id}`, method: 'get' });
}

export function createCategory(data: any) {
  return request<any, ApiResponse>({ url: '/mall/categories', method: 'post', data });
}

export function updateCategory(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/categories/${id}`, method: 'put', data });
}

export function deleteCategory(id: number) {
  return request<any, ApiResponse>({ url: `/mall/categories/${id}`, method: 'delete' });
}
```

- [ ] **Step 12.2: 商家 API**

```ts
// frontend/src/api/mall/merchant.ts
import request from '@/utils/request';

export function getMerchantList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/merchants', method: 'get', params });
}

export function getMerchantDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/merchants/${id}`, method: 'get' });
}

export function createMerchant(data: any) {
  return request<any, ApiResponse>({ url: '/mall/merchants', method: 'post', data });
}

export function updateMerchant(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/merchants/${id}`, method: 'put', data });
}

export function deleteMerchant(id: number) {
  return request<any, ApiResponse>({ url: `/mall/merchants/${id}`, method: 'delete' });
}

export function updateMerchantStatus(id: number, status: string) {
  return request<any, ApiResponse>({ url: `/mall/merchants/${id}/status`, method: 'patch', data: { status } });
}
```

- [ ] **Step 12.3: 商品 API**

```ts
// frontend/src/api/mall/product.ts
import request from '@/utils/request';

export function getProductList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/products', method: 'get', params });
}

export function getProductDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/products/${id}`, method: 'get' });
}

export function createProduct(data: any) {
  return request<any, ApiResponse>({ url: '/mall/products', method: 'post', data });
}

export function updateProduct(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/products/${id}`, method: 'put', data });
}

export function deleteProduct(id: number) {
  return request<any, ApiResponse>({ url: `/mall/products/${id}`, method: 'delete' });
}

export function updateProductStatus(id: number, status: string) {
  return request<any, ApiResponse>({ url: `/mall/products/${id}/status`, method: 'patch', data: { status } });
}

export function getSkuList(productId: number) {
  return request<any, ApiResponse>({ url: `/mall/products/${productId}/skus`, method: 'get' });
}

export function createSku(productId: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/products/${productId}/skus`, method: 'post', data });
}

export function updateSku(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/skus/${id}`, method: 'put', data });
}

export function deleteSku(id: number) {
  return request<any, ApiResponse>({ url: `/mall/skus/${id}`, method: 'delete' });
}
```

- [ ] **Step 12.4: 订单/退款/支付 API**

```ts
// frontend/src/api/mall/order.ts
import request from '@/utils/request';

export function getOrderList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/orders', method: 'get', params });
}

export function getOrderDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/orders/${id}`, method: 'get' });
}

export function updateOrder(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/orders/${id}`, method: 'put', data });
}

export function cancelOrder(id: number) {
  return request<any, ApiResponse>({ url: `/mall/orders/${id}/cancel`, method: 'patch' });
}
```

```ts
// frontend/src/api/mall/refund.ts
import request from '@/utils/request';

export function getRefundList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/refunds', method: 'get', params });
}

export function getRefundDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/refunds/${id}`, method: 'get' });
}

export function updateRefund(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/refunds/${id}`, method: 'put', data });
}

export function deleteRefund(id: number) {
  return request<any, ApiResponse>({ url: `/mall/refunds/${id}`, method: 'delete' });
}

export function approveRefund(id: number) {
  return request<any, ApiResponse>({ url: `/mall/refunds/${id}/approve`, method: 'patch' });
}

export function rejectRefund(id: number, reject_reason: string) {
  return request<any, ApiResponse>({ url: `/mall/refunds/${id}/reject`, method: 'patch', data: { reject_reason } });
}
```

```ts
// frontend/src/api/mall/payment.ts
import request from '@/utils/request';

export function getPaymentList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/payments', method: 'get', params });
}
```

- [ ] **Step 12.5: 配送/配送员/概括 API**

```ts
// frontend/src/api/mall/delivery.ts
import request from '@/utils/request';

export function getDeliveryList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/deliveries', method: 'get', params });
}

export function getDeliveryDetail(id: number) {
  return request<any, ApiResponse>({ url: `/mall/deliveries/${id}`, method: 'get' });
}

export function updateDelivery(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/deliveries/${id}`, method: 'put', data });
}

export function assignDelivery(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/deliveries/${id}/assign`, method: 'patch', data });
}
```

```ts
// frontend/src/api/mall/deliveryStaff.ts
import request from '@/utils/request';

export function getStaffList(params?: any) {
  return request<any, ApiResponse>({ url: '/mall/delivery-staff', method: 'get', params });
}

export function createStaff(data: any) {
  return request<any, ApiResponse>({ url: '/mall/delivery-staff', method: 'post', data });
}

export function updateStaff(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/mall/delivery-staff/${id}`, method: 'put', data });
}

export function deleteStaff(id: number) {
  return request<any, ApiResponse>({ url: `/mall/delivery-staff/${id}`, method: 'delete' });
}
```

```ts
// frontend/src/api/mall/overview.ts
import request from '@/utils/request';

export function getMallOverview() {
  return request<any, ApiResponse>({ url: '/mall/overview', method: 'get' });
}
```

- [ ] **Step 12.6: 提交**

```bash
git add frontend/src/api/category.ts frontend/src/api/mall/
git commit -m "feat(mall): 新增前端 API 封装（商家/商品/订单/退款/支付/配送/概括）"
```

---

## Task 13: 商品分类页面（更新现有）

**Files:**
- Modify: `frontend/src/views/product/category/index.vue`

说明：现有页面已存在且引用 `@/api/category.ts` 的函数名不变。因为 Task 12 已把 URL 改为 `/mall/categories`，此页面代码无需改动，只需验证功能正常。

- [ ] **Step 13.1: 启动前端开发服务器验证**

```bash
cd frontend && npm run dev
```

访问 `http://localhost:5173/product/category`，执行一次：
- 新增分类
- 编辑分类
- 删除子分类
- 尝试删除有子项的父分类，应收到 400 错误提示

- [ ] **Step 13.2: 无须代码变更，该页面跟随 Task 12 API 更新自动生效**

- [ ] **Step 13.3: 跳过提交（无文件变更）**

---

## Task 14: 商家管理页

**Files:**
- Create: `frontend/src/views/mall/merchant/index.vue`

- [ ] **Step 14.1: 新建页面**

```vue
<!-- frontend/src/views/mall/merchant/index.vue -->
<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="商家名称/电话">
          <el-input v-model="queryParams.keywords" placeholder="请输入" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable>
            <el-option label="待审核" value="pending" />
            <el-option label="正常" value="active" />
            <el-option label="禁用" value="disabled" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['mall:merchant:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增商家
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="ID" prop="id" width="80" align="center" />
        <el-table-column label="商家名称" prop="name" min-width="150" />
        <el-table-column label="联系人" prop="contact_name" width="120" />
        <el-table-column label="联系电话" prop="contact_phone" width="140" />
        <el-table-column label="佣金比例(%)" prop="commission_rate" width="110" align="center" />
        <el-table-column label="状态" prop="status" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:merchant:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button v-if="row.status === 'pending'" type="success" link v-hasPerm="['mall:merchant:status']" @click="handleStatus(row, 'active')">通过</el-button>
            <el-button v-if="row.status === 'pending'" type="warning" link v-hasPerm="['mall:merchant:status']" @click="handleStatus(row, 'disabled')">驳回</el-button>
            <el-button v-if="row.status === 'active'" type="warning" link v-hasPerm="['mall:merchant:status']" @click="handleStatus(row, 'disabled')">禁用</el-button>
            <el-button v-if="row.status === 'disabled'" type="success" link v-hasPerm="['mall:merchant:status']" @click="handleStatus(row, 'active')">启用</el-button>
            <el-button type="danger" link v-hasPerm="['mall:merchant:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination v-model:current-page="queryParams.page" v-model:page-size="queryParams.pageSize"
          :page-sizes="[10,20,50,100]" :total="total" layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleQuery" @current-change="handleQuery" />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="商家名称" prop="name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item label="Logo" prop="logo">
          <el-input v-model="formData.logo" placeholder="图片 URL" />
        </el-form-item>
        <el-form-item label="联系人" prop="contact_name">
          <el-input v-model="formData.contact_name" />
        </el-form-item>
        <el-form-item label="联系电话" prop="contact_phone">
          <el-input v-model="formData.contact_phone" />
        </el-form-item>
        <el-form-item label="佣金比例(%)" prop="commission_rate">
          <el-input-number v-model="formData.commission_rate" :min="0" :max="100" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" style="width: 100%">
            <el-option label="待审核" value="pending" />
            <el-option label="正常" value="active" />
            <el-option label="禁用" value="disabled" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeDialog">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import {
  getMerchantList, getMerchantDetail, createMerchant, updateMerchant,
  deleteMerchant, updateMerchantStatus,
} from '@/api/mall/merchant';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();

const queryParams = reactive({ keywords: '', status: '', page: 1, pageSize: 10 });

const formData = reactive({
  id: undefined as number | undefined,
  name: '', logo: '', contact_name: '', contact_phone: '',
  commission_rate: 0, status: 'pending', remark: '',
});

const formRules = {
  name: [{ required: true, message: '请输入商家名称', trigger: 'blur' }],
};

function statusTagType(s: string) {
  return s === 'active' ? 'success' : s === 'pending' ? 'warning' : 'danger';
}
function statusText(s: string) {
  return s === 'active' ? '正常' : s === 'pending' ? '待审核' : '禁用';
}

onMounted(handleQuery);

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getMerchantList(queryParams);
    tableData.value = res.data?.list || [];
    total.value = res.data?.total || 0;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.keywords = '';
  queryParams.status = '';
  queryParams.page = 1;
  handleQuery();
}

async function openDialog(id?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑商家';
    const res = await getMerchantDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增商家';
  }
  dialogVisible.value = true;
}

function closeDialog() { dialogVisible.value = false; resetForm(); }

function resetForm() {
  formData.id = undefined;
  formData.name = ''; formData.logo = ''; formData.contact_name = '';
  formData.contact_phone = ''; formData.commission_rate = 0;
  formData.status = 'pending'; formData.remark = '';
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;
  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateMerchant(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createMerchant(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该商家？', '提示', { type: 'warning' });
  await deleteMerchant(id);
  ElMessage.success('删除成功');
  handleQuery();
}

async function handleStatus(row: any, status: string) {
  await updateMerchantStatus(row.id, status);
  ElMessage.success('状态已更新');
  handleQuery();
}
</script>
```

- [ ] **Step 14.2: 浏览器验证**

```bash
cd frontend && npm run dev
```

访问 `/mall/merchant`，验证：新增、编辑、审核、禁用、启用、删除按钮全部正常。

- [ ] **Step 14.3: 提交**

```bash
git add frontend/src/views/mall/merchant/index.vue
git commit -m "feat(mall): 新增商家管理前端页面"
```

---

## Task 15: 商品列表页

**Files:**
- Create: `frontend/src/views/mall/product/index.vue`

- [ ] **Step 15.1: 新建页面**

```vue
<!-- frontend/src/views/mall/product/index.vue -->
<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="商品名称">
          <el-input v-model="queryParams.keywords" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item label="商家">
          <el-select v-model="queryParams.merchant_id" placeholder="全部" clearable filterable>
            <el-option v-for="m in merchantOptions" :key="m.id" :label="m.name" :value="m.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="queryParams.type" placeholder="全部" clearable>
            <el-option label="实物" value="physical" />
            <el-option label="虚拟" value="virtual" />
            <el-option label="服务" value="service" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable>
            <el-option label="草稿" value="draft" />
            <el-option label="待审核" value="pending" />
            <el-option label="在售" value="active" />
            <el-option label="下架" value="off_shelf" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['mall:product:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增商品
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="ID" prop="id" width="70" align="center" />
        <el-table-column label="商品名称" prop="name" min-width="160" />
        <el-table-column label="商家" min-width="120">
          <template #default="{ row }">{{ row.merchant?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="分类" min-width="100">
          <template #default="{ row }">{{ row.category?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="类型" prop="type" width="80" align="center">
          <template #default="{ row }">{{ typeText(row.type) }}</template>
        </el-table-column>
        <el-table-column label="状态" prop="status" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort" width="70" align="center" />
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:product:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button v-if="row.status === 'pending'" type="success" link v-hasPerm="['mall:product:status']" @click="handleStatus(row, 'active')">审核通过</el-button>
            <el-button v-if="row.status === 'active'" type="warning" link v-hasPerm="['mall:product:status']" @click="handleStatus(row, 'off_shelf')">下架</el-button>
            <el-button v-if="row.status === 'off_shelf'" type="success" link v-hasPerm="['mall:product:status']" @click="handleStatus(row, 'active')">上架</el-button>
            <el-button type="danger" link v-hasPerm="['mall:product:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination v-model:current-page="queryParams.page" v-model:page-size="queryParams.pageSize"
          :page-sizes="[10,20,50,100]" :total="total" layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleQuery" @current-change="handleQuery" />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="720px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="商家" prop="merchant_id">
              <el-select v-model="formData.merchant_id" filterable style="width: 100%">
                <el-option v-for="m in merchantOptions" :key="m.id" :label="m.name" :value="m.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="分类" prop="category_id">
              <el-tree-select v-model="formData.category_id" :data="categoryTree"
                :props="{ label: 'name', children: 'children' }" value-key="id" check-strictly clearable style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="类型" prop="type">
              <el-select v-model="formData.type" style="width: 100%">
                <el-option label="实物" value="physical" />
                <el-option label="虚拟" value="virtual" />
                <el-option label="服务" value="service" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-select v-model="formData.status" style="width: 100%">
                <el-option label="草稿" value="draft" />
                <el-option label="待审核" value="pending" />
                <el-option label="在售" value="active" />
                <el-option label="下架" value="off_shelf" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="封面图" prop="cover">
          <el-input v-model="formData.cover" placeholder="图片 URL" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="formData.sort" :min="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeDialog">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import {
  getProductList, getProductDetail, createProduct, updateProduct,
  deleteProduct, updateProductStatus,
} from '@/api/mall/product';
import { getMerchantList } from '@/api/mall/merchant';
import { getCategoryList } from '@/api/category';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();

const merchantOptions = ref<any[]>([]);
const categoryTree = ref<any[]>([]);

const queryParams = reactive({
  keywords: '', merchant_id: undefined as number | undefined,
  type: '', status: '', page: 1, pageSize: 10,
});

const formData = reactive({
  id: undefined as number | undefined,
  merchant_id: undefined as number | undefined,
  category_id: 0, name: '', type: 'physical',
  cover: '', description: '', status: 'draft', sort: 0,
});

const formRules = {
  merchant_id: [{ required: true, message: '请选择商家', trigger: 'change' }],
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
};

function typeText(t: string) { return { physical: '实物', virtual: '虚拟', service: '服务' }[t] || t; }
function statusTag(s: string) {
  return { draft: 'info', pending: 'warning', active: 'success', off_shelf: 'danger' }[s] || 'info';
}
function statusText(s: string) {
  return { draft: '草稿', pending: '待审核', active: '在售', off_shelf: '下架' }[s] || s;
}

onMounted(async () => {
  await Promise.all([loadMerchants(), loadCategories()]);
  handleQuery();
});

async function loadMerchants() {
  const res = await getMerchantList({ pageSize: 100, status: 'active' });
  merchantOptions.value = res.data?.list || [];
}

async function loadCategories() {
  const res = await getCategoryList();
  categoryTree.value = [{ id: 0, name: '未分类', children: res.data || [] }];
}

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getProductList(queryParams);
    tableData.value = res.data?.list || [];
    total.value = res.data?.total || 0;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  Object.assign(queryParams, { keywords: '', merchant_id: undefined, type: '', status: '', page: 1 });
  handleQuery();
}

async function openDialog(id?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑商品';
    const res = await getProductDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增商品';
  }
  dialogVisible.value = true;
}

function closeDialog() { dialogVisible.value = false; resetForm(); }

function resetForm() {
  formData.id = undefined;
  formData.merchant_id = undefined; formData.category_id = 0;
  formData.name = ''; formData.type = 'physical';
  formData.cover = ''; formData.description = '';
  formData.status = 'draft'; formData.sort = 0;
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;
  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateProduct(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createProduct(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该商品？', '提示', { type: 'warning' });
  await deleteProduct(id);
  ElMessage.success('删除成功');
  handleQuery();
}

async function handleStatus(row: any, status: string) {
  await updateProductStatus(row.id, status);
  ElMessage.success('状态已更新');
  handleQuery();
}
</script>
```

- [ ] **Step 15.2: 浏览器验证 + 提交**

```bash
cd frontend && npm run dev
# 访问 /mall/product 测试完整流程
git add frontend/src/views/mall/product/index.vue
git commit -m "feat(mall): 新增商品管理前端页面"
```

---
