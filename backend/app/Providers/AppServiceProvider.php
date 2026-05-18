<?php

namespace App\Providers;

use App\Events\Mall\OrderPaidEvent;
use App\Listeners\Mall\HandleOrderPaidListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mall 域事件订阅（M06-PR26）
        Event::listen(OrderPaidEvent::class, [HandleOrderPaidListener::class, 'handle']);
    }
}
