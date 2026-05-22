<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Mall\AttributeController;
use App\Http\Controllers\Api\Mall\AttributeValueController;
use App\Http\Controllers\Api\Mall\BrandController;
use App\Http\Controllers\Api\Mall\CategoryController;
use App\Http\Controllers\Api\Mall\CustomerController;
use App\Http\Controllers\Api\Mall\CustomerGroupController;
use App\Http\Controllers\Api\Mall\OrderController as MallOrderController;
use App\Http\Controllers\Api\Mall\OrderShipmentController;
use App\Http\Controllers\Api\Mall\ProductController;
use App\Http\Controllers\Api\Mall\ProductVariantController;
use App\Http\Controllers\Api\Mall\ShippingMethodController;
use App\Http\Controllers\Api\Mall\SpecificationController;
use App\Http\Controllers\Api\Mall\SpecificationValueController;
use App\Http\Controllers\Api\Shop\AuthController as ShopAuthController;
use App\Http\Controllers\Api\Shop\CartController;
use App\Http\Controllers\Api\Shop\CheckoutController;
use App\Http\Controllers\Api\Shop\CustomerAddressController;
use App\Http\Controllers\Api\Shop\CustomerOrderController;
use App\Http\Controllers\Api\Shop\OrderController;
use App\Http\Controllers\Api\Shop\PaymentWebhookController;
use App\Http\Controllers\Api\System\ConfigController;
use App\Http\Controllers\Api\System\CountryController;
use App\Http\Controllers\Api\System\CurrencyController;
use App\Http\Controllers\Api\System\DeptController;
use App\Http\Controllers\Api\System\DictController;
use App\Http\Controllers\Api\System\DictItemController;
use App\Http\Controllers\Api\System\ExchangeRateController;
use App\Http\Controllers\Api\System\LanguageController;
use App\Http\Controllers\Api\System\MenuController;
use App\Http\Controllers\Api\System\NoticeController;
use App\Http\Controllers\Api\System\PlanController;
use App\Http\Controllers\Api\System\RoleController;
use App\Http\Controllers\Api\System\ShopController;
use App\Http\Controllers\Api\System\TenantController;
use App\Http\Controllers\Api\System\UserController;
use App\Http\Controllers\Api\System\UserLogController;
use App\Http\Controllers\Api\System\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // === Shop 前台 API（消费者侧，允许游客）===
    Route::prefix('shop')->group(function () {
        // 客户验证码 / 注册 / 登录（公开端点 + throttle 节流）
        Route::middleware('throttle:5,1')->group(function () {
            Route::post('auth/send-code', [ShopAuthController::class, 'sendCode']);
            Route::post('auth/register', [ShopAuthController::class, 'register']);
            Route::post('auth/login', [ShopAuthController::class, 'login']);
            Route::post('auth/login-by-code', [ShopAuthController::class, 'loginByCode']);
        });

        // 需 customer 身份的端点
        Route::middleware('auth:passport-customer')->group(function () {
            Route::get('auth/me', [ShopAuthController::class, 'me']);
            Route::post('auth/logout', [ShopAuthController::class, 'logout']);

            // 我的地址簿（M09-PR36）
            Route::get('me/addresses', [CustomerAddressController::class, 'index']);
            Route::post('me/addresses', [CustomerAddressController::class, 'store']);
            Route::get('me/addresses/{address}', [CustomerAddressController::class, 'show']);
            Route::put('me/addresses/{address}', [CustomerAddressController::class, 'update']);
            Route::delete('me/addresses/{address}', [CustomerAddressController::class, 'destroy']);

            // 我的订单（M09-PR36）
            Route::get('me/orders', [CustomerOrderController::class, 'index']);
            Route::get('me/orders/{order}', [CustomerOrderController::class, 'show']);
        });

        // 购物车（身份通过 header 解析：X-Tenant-Id + X-Shop-Id + X-Customer-Id/X-Session-Id）
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'addItem']);
        Route::put('cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('cart/items/{item}', [CartController::class, 'removeItem']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::post('cart/merge', [CartController::class, 'merge']);

        // 订单
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);

        // 结账（preview 只读 + place-order 写入，身份解析同购物车/订单）
        Route::get('checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('checkout/place-order', [CheckoutController::class, 'place']);

        // 支付回调统一入口（{paymentMethod} = payment_methods.id）
        Route::post('payment/webhook/{paymentMethod}', [PaymentWebhookController::class, 'handle']);
    });

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/routes', [AuthController::class, 'routes']);

        // System management (with tenant middleware)
        Route::middleware('tenant')->group(function () {
            // 系统管理模块（统一挂到 /api/v1/system/* 前缀下，与 Api\System\ 命名空间对称）
            Route::prefix('system')->group(function () {
                // Users
                Route::apiResource('users', UserController::class);
                Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
                Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

                // Roles
                Route::apiResource('roles', RoleController::class);
                Route::get('roles/{role}/menus', [RoleController::class, 'menus']);
                Route::put('roles/{role}/menus', [RoleController::class, 'updateMenus']);

                // Menus
                Route::apiResource('menus', MenuController::class);

                // Departments
                Route::apiResource('depts', DeptController::class);

                // Dictionaries
                Route::apiResource('dicts', DictController::class);
                Route::get('dicts/{dict}/items', [DictController::class, 'items']);
                Route::apiResource('dict-items', DictItemController::class)->except(['index']);

                // System Config
                Route::apiResource('configs', ConfigController::class);

                // Notices
                Route::apiResource('notices', NoticeController::class);
                Route::patch('notices/{notice}/publish', [NoticeController::class, 'publish']);
                Route::patch('notices/{notice}/revoke', [NoticeController::class, 'revoke']);

                // 用户操作日志
                Route::apiResource('user-logs', UserLogController::class);

                // 租户：超管全量；租户管理员仅本租户（接口内校验）
                Route::apiResource('tenants', TenantController::class);

                // 店铺：跨境多语言站点，归属租户
                Route::apiResource('shops', ShopController::class);

                // 套餐：SaaS 套餐定义（含额度限制）
                Route::apiResource('plans', PlanController::class);

                // 多语言 / 多币种 / 国家基础数据
                Route::apiResource('languages', LanguageController::class);
                Route::apiResource('currencies', CurrencyController::class);
                Route::apiResource('countries', CountryController::class);

                // 区域分组（如 EU, ASEAN, APAC）
                Route::apiResource('zones', ZoneController::class);

                // 汇率（含手动同步入口）
                Route::post('exchange-rates/sync', [ExchangeRateController::class, 'sync']);
                Route::apiResource('exchange-rates', ExchangeRateController::class)
                    ->parameters(['exchange-rates' => 'exchangeRate']);
            });

            // 地区管理（公共地理数据，不属于 system 命名空间）
            Route::apiResource('areas', AreaController::class);

            // === Mall 商城业务模块 ===
            Route::prefix('mall')->group(function () {
                // 规格组（颜色/尺码）+ 嵌套值（红/M）
                Route::apiResource('specifications', SpecificationController::class);
                Route::get('specifications/{specification}/values', [SpecificationValueController::class, 'index']);
                Route::post('specifications/{specification}/values', [SpecificationValueController::class, 'store']);
                Route::get('specification-values/{value}', [SpecificationValueController::class, 'show']);
                Route::put('specification-values/{value}', [SpecificationValueController::class, 'update']);
                Route::delete('specification-values/{value}', [SpecificationValueController::class, 'destroy']);

                // 属性组（材质/产地）+ 嵌套值
                Route::apiResource('attributes', AttributeController::class);
                Route::get('attributes/{attribute}/values', [AttributeValueController::class, 'index']);
                Route::post('attributes/{attribute}/values', [AttributeValueController::class, 'store']);
                Route::get('attribute-values/{value}', [AttributeValueController::class, 'show']);
                Route::put('attribute-values/{value}', [AttributeValueController::class, 'update']);
                Route::delete('attribute-values/{value}', [AttributeValueController::class, 'destroy']);

                // SPU 商品主体（quick-create 必须放在 apiResource 前，否则匹配 show {product}）
                Route::post('products/quick-create', [ProductController::class, 'quickCreate']);
                Route::apiResource('products', ProductController::class);

                // SKU 商品变体（嵌套在 product 下创建/列出，独立端点更新/删除）
                Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
                Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
                Route::post('products/{product}/variants/matrix', [ProductVariantController::class, 'generateMatrix']);
                Route::get('product-variants/{variant}', [ProductVariantController::class, 'show']);
                Route::put('product-variants/{variant}', [ProductVariantController::class, 'update']);
                Route::delete('product-variants/{variant}', [ProductVariantController::class, 'destroy']);

                // 类目树（含拖拽排序，reorder 必须放在 apiResource 前）
                Route::post('categories/reorder', [CategoryController::class, 'reorder']);
                Route::apiResource('categories', CategoryController::class);

                // 品牌
                Route::apiResource('brands', BrandController::class);

                // 物流 / 快递方式（含分段费率 rates）
                Route::apiResource('shipping-methods', ShippingMethodController::class)
                    ->parameters(['shipping-methods' => 'shippingMethod']);

                // 订单发货 / 物流跟踪（一订单可多发货）
                Route::post('order-shipments/{orderShipment}/deliver', [OrderShipmentController::class, 'deliver']);
                Route::post('order-shipments/{orderShipment}/cancel', [OrderShipmentController::class, 'cancel']);
                Route::apiResource('order-shipments', OrderShipmentController::class)
                    ->parameters(['order-shipments' => 'orderShipment'])
                    ->except(['destroy']);

                // 后台订单管理（list / show + ship/refund/cancel 三个动作端点）
                Route::post('orders/{order}/ship', [MallOrderController::class, 'ship']);
                Route::post('orders/{order}/refund', [MallOrderController::class, 'refund']);
                Route::post('orders/{order}/cancel', [MallOrderController::class, 'cancel']);
                Route::apiResource('orders', MallOrderController::class)->only(['index', 'show']);

                // 客户分组（请放在 customers 前，避免被路由匹配为 customer）
                Route::apiResource('customer-groups', CustomerGroupController::class)
                    ->parameters(['customer-groups' => 'customerGroup']);

                // 后台客户管理（仅 list/show/update/destroy，不开放 store）
                Route::apiResource('customers', CustomerController::class)
                    ->only(['index', 'show', 'update', 'destroy']);
            });
        });
    });
});
