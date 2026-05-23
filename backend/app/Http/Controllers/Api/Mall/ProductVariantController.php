<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\Api\Mall\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\Api\Mall\ProductVariantResource;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Services\Api\Mall\ProductVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantService $service) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductAccess($request, $product);

        $variants = $this->service->listForProduct($product);

        return $this->success(
            ProductVariantResource::collection($variants)->toArray($request)
        );
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->ensureProductAccess($request, $product);

        $variant = $this->service->create($product, $request->validated());

        return $this->success(new ProductVariantResource($variant), 'api.created');
    }

    public function show(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantAccess($request, $variant);

        return $this->success(new ProductVariantResource(
            $variant->load('specificationValues.translations')
        ));
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantAccess($request, $variant);

        $this->service->update($variant, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantAccess($request, $variant);

        $this->service->delete($variant);

        return $this->success(null, 'api.deleted');
    }

    /**
     * 矩阵生成端点：给定多个规格组的 value_ids，返回笛卡尔积组合（用于前端预览）。
     */
    public function generateMatrix(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductAccess($request, $product);

        $request->validate([
            'spec_groups' => 'required|array|min:1',
            'spec_groups.*' => 'array|min:1',
            'spec_groups.*.*' => 'integer|min:1',
        ]);

        $combinations = $this->service->generateMatrix($request->input('spec_groups', []));

        return $this->success([
            'count' => count($combinations),
            'combinations' => $combinations,
        ]);
    }

    private function ensureProductAccess(Request $request, Product $product): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $product->tenant_id) {
            abort(403);
        }
    }

    private function ensureVariantAccess(Request $request, ProductVariant $variant): void
    {
        $variant->loadMissing('product');
        /** @var Product|null $product */
        $product = $variant->product;
        if ($product === null) {
            abort(404);
        }
        $this->ensureProductAccess($request, $product);
    }
}
