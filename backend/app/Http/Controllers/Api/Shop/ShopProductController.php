<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\Shop\ShopProductResource;
use App\Models\Mall\Product;
use App\Models\Mall\ProductTranslation;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商城前台商品接口（M11-PR43）：仅展示当前店铺所属租户的上架商品（status=1）。
 *
 * 路由 `GET /api/v1/shop/products` / `GET /api/v1/shop/products/{product}` 需通过 `shop` 中间件。
 *
 * 过滤参数：category_id / brand_id / keywords（按当前语言翻译命中 name）
 * 分页：pageNum / pageSize（最大 60）
 */
class ShopProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->requireShop($request);

        $pageSize = (int) $request->input('pageSize', 20);
        $pageSize = max(1, min($pageSize, 60));
        $pageNum = (int) $request->input('pageNum', 1);
        $pageNum = max(1, $pageNum);

        $query = Product::query()
            ->with(['translations'])
            ->where('tenant_id', $shop->tenant_id)
            ->where('status', 1);

        // shop 范围：当前请求的 shop_id 命中，或 shop_id=null（租户级共享商品）
        $query->where(function ($q) use ($shop) {
            $q->where('shop_id', $shop->id)->orWhereNull('shop_id');
        });

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $brandId = (int) $request->query('brand_id', 0);
        if ($brandId > 0) {
            $query->where('brand_id', $brandId);
        }

        $keywords = trim((string) $request->query('keywords', ''));
        if ($keywords !== '') {
            $query->whereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$keywords}%"));
        }

        $paginator = $query->orderByDesc('sort')
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $pageNum);

        return $this->paginate($paginator, ShopProductResource::class);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $shop = $this->requireShop($request);
        $this->ensureVisible($product, $shop);

        $product->load(['translations', 'variants.specificationValues.translations']);

        return $this->success(new ShopProductResource($product));
    }

    /**
     * 按 slug 查找商品（M11-PR44 SEO 详情页）。
     *
     * 解析顺序：
     *   1. 当前 X-Locale + 该 slug 命中的翻译
     *   2. 该 slug 命中任意 locale 的翻译（多语言别名共用同一商品时不丢访问）
     *
     * 命中后复用 ensureVisible 做 tenant + shop + status=1 三重校验。
     */
    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $shop = $this->requireShop($request);
        $locale = (string) ($request->header('X-Locale') ?? '');

        $translationQuery = ProductTranslation::query()
            ->where('slug', $slug)
            ->whereHas('product', function ($q) use ($shop) {
                $q->where('tenant_id', $shop->tenant_id)
                    ->where('status', 1)
                    ->where(function ($qq) use ($shop) {
                        $qq->where('shop_id', $shop->id)->orWhereNull('shop_id');
                    });
            });

        $translation = null;
        if ($locale !== '') {
            $translation = (clone $translationQuery)->where('locale', $locale)->first();
        }
        if ($translation === null) {
            $translation = $translationQuery->first();
        }

        if ($translation === null) {
            abort(404);
        }

        $product = Product::with(['translations', 'variants.specificationValues.translations'])
            ->find($translation->product_id);
        if ($product === null) {
            abort(404);
        }
        // 双重保险：translation 关联可能因软删除等异常发生不一致
        $this->ensureVisible($product, $shop);

        return $this->success(new ShopProductResource($product));
    }

    private function ensureVisible(Product $product, Shop $shop): void
    {
        if ((int) $product->tenant_id !== (int) $shop->tenant_id) {
            abort(404);
        }
        if ((int) $product->status !== 1) {
            abort(404);
        }
        // 商品要么属于当前 shop，要么是租户级共享（shop_id=null）
        if ($product->shop_id !== null && (int) $product->shop_id !== (int) $shop->id) {
            abort(404);
        }
    }

    private function requireShop(Request $request): Shop
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            abort(400, 'shop_not_resolved');
        }

        return $shop;
    }
}
