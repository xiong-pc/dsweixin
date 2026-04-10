<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant'      => \App\Http\Middleware\TenantMiddleware::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = 500;
                $msg = __('api.error');

                if ($e instanceof \App\Exceptions\BusinessException) {
                    $status = $e->getStatusCode();
                    // message 支持语言包 key 或直接字符串
                    $msg = __($e->getMessage()) !== $e->getMessage()
                        ? __($e->getMessage())
                        : $e->getMessage();
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $msg = $e->validator->errors()->first();
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $status = 401;
                    $msg = __('api.unauthorized');
                } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $status = 403;
                    $msg = __('api.forbidden');
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $status = 404;
                    $msg = __('api.not_found');
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    $status = 405;
                    $msg = __('api.method_not_allowed');
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $msg = $e->getMessage() ?: $msg;
                }

                return response()->json([
                    'code' => $status,
                    'msg' => $msg,
                ], $status);
            }
        });
    })->create();
