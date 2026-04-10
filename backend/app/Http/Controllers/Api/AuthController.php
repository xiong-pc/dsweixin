<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\LoginRequest;
use App\Services\Api\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->service->attemptLogin($request->username, $request->password);

        if (!$user) {
            return $this->error('api.invalid_credentials', 400);
        }

        if ($user->status !== 1) {
            return $this->error('api.account_disabled', 403);
        }

        return $this->success($this->service->createToken($user), 'api.login_success');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->success(null, 'api.logout_success');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->service->getUserProfile($request->user()));
    }

    public function routes(Request $request): JsonResponse
    {
        return $this->success($this->service->getRouteTree($request->user()));
    }

    public function refresh(Request $request): JsonResponse
    {
        // 通过 Bearer Token 获取当前用户（路由无 auth 中间件，手动解析）
        $user = auth('api')->user();

        if (!$user) {
            return $this->error('api.unauthorized', 401);
        }

        // 吊销旧 token，颁发新 token（token 轮换）
        $user->token()?->revoke();

        return $this->success($this->service->createToken($user));
    }
}
