<?php

namespace App\Services\Api\Payment\Stripe;

use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\StripeClient as StripeSdkClient;
use Stripe\Webhook;

/**
 * StripeClient 的真实实现，调用 stripe/stripe-php SDK。
 *
 * 每次调用都用动态 apiKey（来自 PaymentMethod.config.api_key）实例化 SDK client，
 * 这样多租户场景下不同租户用各自的 Stripe 账号互不干扰。
 */
class StripeApiClient implements StripeClient
{
    public function createCheckoutSession(string $apiKey, array $params): array
    {
        $sdk = new StripeSdkClient(['api_key' => $apiKey]);
        /** @var Session $session */
        $session = $sdk->checkout->sessions->create($params);

        return [
            'id' => (string) $session->id,
            'url' => (string) ($session->url ?? ''),
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : null,
            'raw' => $session->toArray(),
        ];
    }

    public function createRefund(string $apiKey, array $params): array
    {
        $sdk = new StripeSdkClient(['api_key' => $apiKey]);
        /** @var Refund $refund */
        $refund = $sdk->refunds->create($params);

        return [
            'id' => (string) $refund->id,
            'status' => (string) ($refund->status ?? ''),
            'amount' => (int) ($refund->amount ?? 0),
            'raw' => $refund->toArray(),
        ];
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): array
    {
        // 抛 SignatureVerificationException / UnexpectedValueException 由调用方捕获
        $event = Webhook::constructEvent($payload, $sigHeader, $secret);

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'data' => $event->data->toArray(),
            'raw' => $event->toArray(),
        ];
    }
}
