<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Product\StoreProductRequest;
use App\Http\Requests\Api\Mall\Product\UpdateProductRequest;
use App\Http\Resources\Api\Mall\ProductResource;
use App\Models\Mall\Product;
use App\Services\Api\Mall\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['shop_id', 'brand_id', 'category_id', 'status', 'keywords']);

        if ($user !== null && $user->isSuperAdmin() !== true) {
            $filters['tenant_id'] = (int) $user->tenant_id;
        } elseif ($request->filled('tenant_id')) {
            $filters['tenant_id'] = (int) $request->input('tenant_id');
        }

        return $this->paginate(
            $this->service->list(
                $filters,
                (int) $request->input('pageSize', 20),
                (int) $request->input('pageNum', 1)
            ),
            ProductResource::class
        );
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $data = $request->validated();

        $shopId = $this->normalizeShopId($data['shop_id'] ?? null);
        $errors = $this->service->validateSlugUniqueness(
            $tenantId,
            $shopId,
            $data['translations'] ?? []
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $product = $this->service->create($tenantId, $data);

        return $this->success(new ProductResource($product), 'api.created');
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->ensureTenantAccess($request, $product);

        return $this->success(new ProductResource($product->load('translations')));
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->ensureTenantAccess($request, $product);
        $data = $request->validated();

        if (isset($data['translations']) && is_array($data['translations'])) {
            $shopId = array_key_exists('shop_id', $data)
                ? $this->normalizeShopId($data['shop_id'])
                : $product->shop_id;
            $errors = $this->service->validateSlugUniqueness(
                (int) $product->tenant_id,
                $shopId,
                $data['translations'],
                (int) $product->id
            );

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $this->service->update($product, $data);

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->ensureTenantAccess($request, $product);

        $this->service->delete($product);

        return $this->success(null, 'api.deleted');
    }

    private function resolveTenantId(Request $request): int
    {
        $user = $request->user();
        if ($user !== null && $user->isSuperAdmin() === true) {
            $forced = $request->input('tenant_id');

            return $forced !== null ? (int) $forced : (int) ($user->tenant_id ?? 0);
        }

        return (int) ($user->tenant_id ?? 0);
    }

    private function ensureTenantAccess(Request $request, Product $product): void
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

    private function normalizeShopId(mixed $shopId): ?int
    {
        if ($shopId === null || $shopId === '' || $shopId === 0 || $shopId === '0') {
            return null;
        }

        return (int) $shopId;
    }
}
