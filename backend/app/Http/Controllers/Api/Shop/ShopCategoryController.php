<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\Shop\ShopCategoryResource;
use App\Models\Mall\Category;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商城前台类目接口（M11-PR43）：仅展示当前店铺所属租户的启用类目。
 *
 * 路由 `GET /api/v1/shop/categories` 需通过 `shop` 中间件解析 host/header → 注入 attributes。
 */
class ShopCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return $this->error('api.shop_not_resolved', 400);
        }

        $categories = Category::query()
            ->where('tenant_id', $shop->tenant_id)
            ->where('status', 1)
            ->with(['translations'])
            ->orderBy('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $this->success(ShopCategoryResource::collection($categories));
    }
}
