<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['code' => 401, 'msg' => __('api.unauthorized')], 401);
        }

        // 仅 admin User（auth:api 后置）需要走租户校验；
        // customer guard 路由不会经过此中间件，但保险地做类型收窄。
        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->tenant_id && $user->tenant) {
            $tenant = $user->tenant;
            if ($tenant->status !== 1) {
                return response()->json(['code' => 403, 'msg' => __('api.tenant_disabled')], 403);
            }
            if ($tenant->expired_at && $tenant->expired_at->isPast()) {
                return response()->json(['code' => 403, 'msg' => __('api.tenant_expired')], 403);
            }
        }

        return $next($request);
    }
}
