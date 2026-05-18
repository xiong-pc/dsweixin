<?php

namespace App\Providers;

use App\Events\Mall\OrderPaidEvent;
use App\Listeners\Mall\HandleOrderPaidListener;
use App\Services\Api\Payment\Stripe\StripeApiClient;
use App\Services\Api\Payment\Stripe\StripeClient;
use App\Services\Api\Payment\Wechat\WechatApiClient;
use App\Services\Api\Payment\Wechat\WechatClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 默认 Stripe / Wechat 客户端：真实 SDK 调用；测试可在 setUp 用 $this->app->bind 覆盖
        $this->app->bind(StripeClient::class, StripeApiClient::class);
        $this->app->bind(WechatClient::class, WechatApiClient::class);
    }

    public function boot(): void
    {
        // Mall 域事件订阅（M06-PR26）
        Event::listen(OrderPaidEvent::class, [HandleOrderPaidListener::class, 'handle']);
    }
}
