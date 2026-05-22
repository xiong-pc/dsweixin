<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 公开端点：返回当前请求解析出的店铺基础配置（无需 customer 登录）。
 *
 * 由 `shop` 中间件（ShopResolverMiddleware）根据 host 子域 / `X-Shop-Subdomain` header
 * 解析 Shop + Tenant，写入 request attributes。本控制器只是把它格式化输出。
 *
 * 主要消费方：Nuxt SSR 前台 `middleware/tenant.global.ts`，启动期拉取此端点
 * 决定主题色 / 默认语言 / 默认币种等。
 */
class ShopConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return $this->error('api.shop_not_resolved', 400);
        }

        return $this->success([
            'tenant_id' => (int) $shop->tenant_id,
            'shop_id' => (int) $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'subdomain' => $shop->subdomain,
            'locale' => $shop->locale,
            'currency' => $shop->currency,
            'timezone' => $shop->timezone,
            'theme_id' => (int) $shop->theme_id,
            'status' => (int) $shop->status,
        ]);
    }
}
