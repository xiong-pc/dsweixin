<?php

namespace Tests\Support\Payment;

use App\Services\Api\Payment\Stripe\StripeClient;

/**
 * 测试用 StripeClient —— 不调真 Stripe API，记录调用 + 按 setup 返回预设响应。
 *
 * 使用方式（test setUp）：
 *   $fake = new FakeStripeClient();
 *   $fake->nextCheckoutSession = ['id' => 'cs_test_1', 'url' => 'https://stripe/co', ...];
 *   $this->app->instance(StripeClient::class, $fake);
 */
class FakeStripeClient implements StripeClient
{
    /** @var array<string, mixed>|null 下一次 createCheckoutSession 的预设返回 */
    public ?array $nextCheckoutSession = null;

    /** @var array<string, mixed>|null 下一次 createRefund 的预设返回 */
    public ?array $nextRefund = null;

    /** @var array<string, mixed>|null 下一次 constructWebhookEvent 的预设返回，null 表示抛 SignatureVerificationException */
    public ?array $nextEvent = null;

    public bool $shouldFailSignature = false;

    /** @var array<int, array{key: string, params: array<string, mixed>}> */
    public array $checkoutCalls = [];

    /** @var array<int, array{key: string, params: array<string, mixed>}> */
    public array $refundCalls = [];

    /** @var array<int, array{payload: string, sig: string, secret: string}> */
    public array $webhookCalls = [];

    public function createCheckoutSession(string $apiKey, array $params): array
    {
        $this->checkoutCalls[] = ['key' => $apiKey, 'params' => $params];

        return $this->nextCheckoutSession ?? [
            'id' => 'cs_test_default',
            'url' => 'https://checkout.stripe.com/c/pay/default',
            'payment_intent' => 'pi_test_default',
            'raw' => ['mocked' => true],
        ];
    }

    public function createRefund(string $apiKey, array $params): array
    {
        $this->refundCalls[] = ['key' => $apiKey, 'params' => $params];

        return $this->nextRefund ?? [
            'id' => 're_test_default',
            'status' => 'succeeded',
            'amount' => (int) ($params['amount'] ?? 0),
            'raw' => ['mocked' => true],
        ];
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): array
    {
        $this->webhookCalls[] = ['payload' => $payload, 'sig' => $sigHeader, 'secret' => $secret];

        if ($this->shouldFailSignature) {
            throw new \UnexpectedValueException('fake_invalid_signature');
        }

        return $this->nextEvent ?? [
            'id' => 'evt_test_default',
            'type' => 'checkout.session.completed',
            'data' => ['object' => []],
            'raw' => ['mocked' => true],
        ];
    }
}
