<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::post('auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('auth/refresh', [\App\Http\Controllers\Api\AuthController::class, 'refresh']);

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('auth/me', [\App\Http\Controllers\Api\AuthController::class, 'me']);
        Route::get('auth/routes', [\App\Http\Controllers\Api\AuthController::class, 'routes']);

        // System management (with tenant middleware)
        Route::middleware('tenant')->group(function () {
            // Users
            Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
            Route::patch('users/{user}/status', [\App\Http\Controllers\Api\UserController::class, 'updateStatus']);
            Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\UserController::class, 'resetPassword']);

            // Roles
            Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
            Route::get('roles/{role}/menus', [\App\Http\Controllers\Api\RoleController::class, 'menus']);
            Route::put('roles/{role}/menus', [\App\Http\Controllers\Api\RoleController::class, 'updateMenus']);

            // Menus
            Route::apiResource('menus', \App\Http\Controllers\Api\MenuController::class);

            // Departments
            Route::apiResource('depts', \App\Http\Controllers\Api\DeptController::class);

            // Dictionaries
            Route::apiResource('dicts', \App\Http\Controllers\Api\DictController::class);
            Route::get('dicts/{dict}/items', [\App\Http\Controllers\Api\DictController::class, 'items']);
            Route::apiResource('dict-items', \App\Http\Controllers\Api\DictItemController::class)->except(['index']);

            // System Config
            Route::apiResource('configs', \App\Http\Controllers\Api\ConfigController::class);

            // Notices
            Route::apiResource('notices', \App\Http\Controllers\Api\NoticeController::class);
            Route::patch('notices/{notice}/publish', [\App\Http\Controllers\Api\NoticeController::class, 'publish']);
            Route::patch('notices/{notice}/revoke', [\App\Http\Controllers\Api\NoticeController::class, 'revoke']);

            // 租户：超管全量；租户管理员仅本租户（接口内校验）
            Route::apiResource('tenants', \App\Http\Controllers\Api\TenantController::class);
        });
    });
});
