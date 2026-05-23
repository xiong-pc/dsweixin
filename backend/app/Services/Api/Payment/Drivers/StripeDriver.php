<?php

namespace App\Services\Api\Payment\Drivers;

use App\Contracts\Payment\PaymentResult;
use App\Contracts\Payment\RefundResult;
use App\Contracts\Payment\WebhookResult;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Services\Api\Payment\Stripe\StripeClient;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripe Checkout Session 驱动。
 *
 * - charge：创建 hosted Checkout Session，返回 pay_url 让前端跳转
 * - refund：通过 OrderPayment.raw_response 提取 payment_intent_id 调 Refund API
 * - handleWebhook：验签后归一化 checkout.session.completed / payment_intent.succeeded
 *   等事件到 WebhookResult
 *
 * config（从 PaymentMethod.config 读）：
 *   - api_key:        sk_live_... / sk_test_...
 *   - webhook_secret: whsec_...
 *   - success_url:    可选默认跳转地址（也可以 charge() 传 params 覆盖）
 *   - cancel_url:     同上
 */
class StripeDriver extends AbstractPaymentDriver
{
    public function __construct(
        PaymentMethod $method,
        private readonly StripeClient $stripe,
    ) {
        parent::__construct($method);
    }

    public function charge(Order $order, array $params = []): PaymentResult
    {
        $apiKey = (string) $this->config('api_key', '');
        if ($apiKey === '') {
            throw new BusinessException('api.payment_driver_unavailable');
        }

        $successUrl = (string) ($params['success_url'] ?? $this->config('success_url', ''));
        $cancelUrl = (string) ($params['cancel_url'] ?? $this->config('cancel_url', ''));
        if ($successUrl === '' || $cancelUrl === '') {
            throw new BusinessException('api.stripe_redirect_url_missing');
        }

        $currency = strtolower((string) $order->currency);
        $lineItems = $this->buildLineItems($order, $currency);
        if ($lineItems === []) {
            throw new BusinessException('api.cart_is_empty');
        }

        $sessionParams = [
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_no' => (string) $order->order_no,
                'order_id' => (string) $order->id,
                'tenant_id' => (string) $order->tenant_id,
            ],
            'client_reference_id' => (string) $order->order_no,
        ];

        $resp = $this->stripe->createCheckoutSession($apiKey, $sessionParams);

        return new PaymentResult(
            success: true,
            transactionId: $resp['id'],
            payUrl: $resp['url'],
            message: '',
            raw: $resp['raw'],
        );
    }

    public function refund(OrderPayment $payment, float $amount): RefundResult
    {
        $apiKey = (string) $this->config('api_key', '');
        if ($apiKey === '') {
            throw new BusinessException('api.payment_driver_unavailable');
        }

        $raw = is_array($payment->raw_response) ? $payment->raw_response : [];
        $paymentIntent = (string) ($raw['payment_intent'] ?? $raw['data']['payment_intent'] ?? '');
        if ($paymentIntent === '') {
            throw new BusinessException('api.stripe_payment_intent_missing');
        }

        $resp = $this->stripe->createRefund($apiKey, [
            'payment_intent' => $paymentIntent,
            // Stripe 用最小货币单位（cents），按 currency 转换
            'amount' => $this->toMinorUnits($amount, (string) $payment->currency),
        ]);

        $success = in_array($resp['status'], ['succeeded', 'pending'], true);

        return new RefundResult(
            success: $success,
            refundId: $resp['id'],
            amount: $amount,
            message: $success ? '' : $resp['status'],
            raw: $resp['raw'],
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $secret = (string) $this->config('webhook_secret', '');
        if ($secret === '') {
            return WebhookResult::unknown(['reason' => 'webhook_secret_missing']);
        }

        $payload = (string) $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        if ($sigHeader === '') {
            return WebhookResult::unknown(['reason' => 'missing_signature']);
        }

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            return new WebhookResult(
                success: false,
                eventType: WebhookResult::EVENT_UNKNOWN,
                message: $e->getMessage(),
                raw: ['reason' => 'invalid_signature'],
            );
        }

        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        return match ($event['type']) {
            'checkout.session.completed' => $this->parseCheckoutCompleted($object, $event),
            'payment_intent.succeeded' => $this->parsePaymentIntentSucceeded($object, $event),
            'payment_intent.payment_failed' => $this->parsePaymentIntentFailed($object, $event),
            'charge.refunded' => $this->parseChargeRefunded($object, $event),
            default => new WebhookResult(
                success: true,
                eventType: WebhookResult::EVENT_UNKNOWN,
                message: 'unhandled_event:'.$event['type'],
                raw: $event['raw'],
            ),
        };
    }

    /**
     * 把订单的 items 转为 Stripe Checkout line_items。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(Order $order, string $currency): array
    {
        $items = [];
        /** @var OrderItem $item */
        foreach ($order->items()->get() as $item) {
            $unitMinor = $this->toMinorUnits((float) $item->unit_price, $currency);
            $items[] = [
                'quantity' => (int) $item->quantity,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitMinor,
                    'product_data' => [
                        'name' => (string) ($item->name_snapshot ?: $item->sku),
                    ],
                ],
            ];
        }

        return $items;
    }

    /**
     * 金额转为最小货币单位（如 USD/CNY 一般 ×100，JPY/KRW 不带小数则 ×1）。
     */
    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['JPY', 'KRW', 'VND'];

        if (in_array(strtoupper($currency), $zeroDecimal, true)) {
            return (int) round($amount, 0);
        }

        return (int) round($amount * 100);
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $event
     */
    private function parseCheckoutCompleted(array $session, array $event): WebhookResult
    {
        $sessionId = (string) ($session['id'] ?? '');
        $orderNo = (string) ($session['client_reference_id'] ?? $session['metadata']['order_no'] ?? '');
        $amount = (int) ($session['amount_total'] ?? 0);
        $currency = (string) ($session['currency'] ?? 'cny');

        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_PAYMENT_SUCCESS,
            transactionId: $sessionId,
            orderNo: $orderNo !== '' ? $orderNo : null,
            amount: $this->fromMinorUnits($amount, $currency),
            raw: array_merge($event['raw'], ['payment_intent' => $session['payment_intent'] ?? null]),
        );
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $event
     */
    private function parsePaymentIntentSucceeded(array $intent, array $event): WebhookResult
    {
        $intentId = (string) ($intent['id'] ?? '');
        $orderNo = (string) ($intent['metadata']['order_no'] ?? '');
        $amount = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
        $currency = (string) ($intent['currency'] ?? 'cny');

        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_PAYMENT_SUCCESS,
            transactionId: $intentId,
            orderNo: $orderNo !== '' ? $orderNo : null,
            amount: $this->fromMinorUnits($amount, $currency),
            raw: $event['raw'],
        );
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $event
     */
    private function parsePaymentIntentFailed(array $intent, array $event): WebhookResult
    {
        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_PAYMENT_FAILED,
            transactionId: (string) ($intent['id'] ?? ''),
            orderNo: (string) ($intent['metadata']['order_no'] ?? '') ?: null,
            message: (string) ($intent['last_payment_error']['message'] ?? ''),
            raw: $event['raw'],
        );
    }

    /**
     * @param  array<string, mixed>  $charge
     * @param  array<string, mixed>  $event
     */
    private function parseChargeRefunded(array $charge, array $event): WebhookResult
    {
        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_REFUND_COMPLETED,
            transactionId: (string) ($charge['payment_intent'] ?? $charge['id'] ?? ''),
            orderNo: (string) ($charge['metadata']['order_no'] ?? '') ?: null,
            amount: $this->fromMinorUnits((int) ($charge['amount_refunded'] ?? 0), (string) ($charge['currency'] ?? 'cny')),
            raw: $event['raw'],
        );
    }

    private function fromMinorUnits(int $minor, string $currency): float
    {
        $zeroDecimal = ['JPY', 'KRW', 'VND'];

        if (in_array(strtoupper($currency), $zeroDecimal, true)) {
            return (float) $minor;
        }

        return round($minor / 100, 2);
    }
}
