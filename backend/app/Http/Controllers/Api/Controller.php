<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller extends BaseController
{
    /**
     * 成功响应
     *
     * @param  mixed   $data  原始数据或 JsonResource 实例
     * @param  string  $msg   语言包 key 或直接传字符串
     * @param  int     $code
     */
    protected function success(mixed $data = null, string $msg = 'api.success', int $code = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg'  => __($msg),
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
        ]);
    }

    /**
     * 失败响应
     *
     * @param  string  $msg   语言包 key 或直接传字符串
     * @param  int     $code
     * @param  mixed   $data
     */
    protected function error(string $msg = 'api.error', int $code = 400, mixed $data = null): JsonResponse
    {
        $payload = [
            'code' => $code,
            'msg'  => __($msg),
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * 分页响应，支持可选的 Resource 类转换列表项
     *
     * @param  LengthAwarePaginator  $paginator
     * @param  class-string<JsonResource>|null  $resourceClass  Resource 类名，如 UserResource::class
     * @param  string  $msg
     */
    protected function paginate(
        LengthAwarePaginator $paginator,
        ?string $resourceClass = null,
        string $msg = 'api.success'
    ): JsonResponse {
        $items = $paginator->items();

        if ($resourceClass !== null) {
            $items = array_map(fn($item) => (new $resourceClass($item))->resolve(), $items);
        }

        return response()->json([
            'code' => 200,
            'msg'  => __($msg),
            'data' => [
                'list'     => $items,
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
            ],
        ]);
    }
}
