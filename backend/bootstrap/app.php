<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            'tenant' => TenantMiddleware::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = 500;
                $msg = __('api.error');

                if ($e instanceof BusinessException) {
                    $status = $e->getStatusCode();
                    // message 支持语言包 key 或直接字符串
                    $msg = __($e->getMessage()) !== $e->getMessage()
                        ? __($e->getMessage())
                        : $e->getMessage();
                } elseif ($e instanceof ValidationException) {
                    $status = 422;
                    $msg = $e->validator->errors()->first();
                } elseif ($e instanceof AuthenticationException) {
                    $status = 401;
                    $msg = __('api.unauthorized');
                } elseif ($e instanceof AuthorizationException) {
                    $status = 403;
                    $msg = __('api.forbidden');
                } elseif ($e instanceof NotFoundHttpException) {
                    $status = 404;
                    $msg = __('api.not_found');
                } elseif ($e instanceof MethodNotAllowedHttpException) {
                    $status = 405;
                    $msg = __('api.method_not_allowed');
                } elseif ($e instanceof HttpException) {
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
