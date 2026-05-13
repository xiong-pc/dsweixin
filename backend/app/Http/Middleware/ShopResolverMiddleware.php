<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopResolverMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->resolveSubdomain($request);

        if ($subdomain === null) {
            return response()->json([
                'code' => 400,
                'msg' => __('api.shop_not_resolved'),
            ], 400);
        }

        $shop = Shop::withoutGlobalScopes()
            ->where('subdomain', $subdomain)
            ->where('status', 1)
            ->first();

        if (! $shop) {
            return response()->json([
                'code' => 404,
                'msg' => __('api.shop_not_found'),
            ], 404);
        }

        $tenant = $shop->tenant;

        if (! $tenant || $tenant->status !== 1) {
            return response()->json([
                'code' => 403,
                'msg' => __('api.tenant_disabled'),
            ], 403);
        }

        if ($tenant->expired_at && $tenant->expired_at->isPast()) {
            return response()->json([
                'code' => 403,
                'msg' => __('api.tenant_expired'),
            ], 403);
        }

        $request->attributes->set('shop', $shop);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function resolveSubdomain(Request $request): ?string
    {
        $headerName = config('mall.shop_header', 'X-Shop-Subdomain');
        $fromHeader = $request->header($headerName);
        if (is_string($fromHeader) && $fromHeader !== '') {
            return $this->sanitize($fromHeader);
        }

        $host = $request->getHost();
        $platformDomain = (string) config('mall.platform_domain', '');

        if ($platformDomain === '' || $host === $platformDomain) {
            return null;
        }

        if (! str_ends_with($host, '.'.$platformDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.'.$platformDomain));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        $reserved = (array) config('mall.reserved_subdomains', []);
        if (in_array(strtolower($subdomain), $reserved, true)) {
            return null;
        }

        return $this->sanitize($subdomain);
    }

    private function sanitize(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || ! preg_match('/^[a-z0-9][a-z0-9\-]{0,62}[a-z0-9]?$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
