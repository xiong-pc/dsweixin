<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DeptController;
use App\Http\Controllers\Api\DictController;
use App\Http\Controllers\Api\DictItemController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/routes', [AuthController::class, 'routes']);

        // System management (with tenant middleware)
        Route::middleware('tenant')->group(function () {
            // Users
            Route::apiResource('users', UserController::class);
            Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

            // Roles
            Route::apiResource('roles', RoleController::class);
            Route::get('roles/{role}/menus', [RoleController::class, 'menus']);
            Route::put('roles/{role}/menus', [RoleController::class, 'updateMenus']);

            // Menus
            Route::apiResource('menus', MenuController::class);

            // Departments
            Route::apiResource('depts', DeptController::class);

            // Dictionaries
            Route::apiResource('dicts', DictController::class);
            Route::get('dicts/{dict}/items', [DictController::class, 'items']);
            Route::apiResource('dict-items', DictItemController::class)->except(['index']);

            // System Config
            Route::apiResource('configs', ConfigController::class);

            // Notices
            Route::apiResource('notices', NoticeController::class);
            Route::patch('notices/{notice}/publish', [NoticeController::class, 'publish']);
            Route::patch('notices/{notice}/revoke', [NoticeController::class, 'revoke']);

            // 地区管理
            Route::apiResource('areas', AreaController::class);

            // 用户操作日志
            Route::apiResource('user-logs', UserLogController::class);

            // 租户：超管全量；租户管理员仅本租户（接口内校验）
            Route::apiResource('tenants', TenantController::class);
        });
    });
});
