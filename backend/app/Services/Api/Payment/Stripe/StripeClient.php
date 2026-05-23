<?php

namespace App\Services\Api\Payment\Stripe;

use Stripe\Exception\SignatureVerificationException;
use Tests\Support\Payment\FakeStripeClient;

/**
 * Stripe SDK 的薄包装契约。
 *
 * 抽象目的：让 StripeDriver 不直接静态调用 \Stripe\Checkout\Session::create() 等，
 * 测试时可注入 fake 实现而无需真连 Stripe / 启动 stripe-mock 容器。
 *
 * 真实实现 {@see StripeApiClient} 用 stripe/stripe-php 调用真接口；
 * 测试用 {@see FakeStripeClient}（在 tests/ 下）注入响应。
 */
interface StripeClient
{
    /**
     * 创建 Checkout Session（hosted 收银台）。
     *
     * @param  array<string, mixed>  $params  Stripe Session::create 入参
     * @return array{id: string, url: string, payment_intent: ?string, raw: array<string, mixed>}
     */
    public function createCheckoutSession(string $apiKey, array $params): array;

    /**
     * 创建 Refund。
     *
     * @param  array<string, mixed>  $params  Stripe Refund::create 入参（如 payment_intent + amount）
     * @return array{id: string, status: string, amount: int, raw: array<string, mixed>}
     */
    public function createRefund(string $apiKey, array $params): array;

    /**
     * 验签并解析 webhook 事件。
     *
     * 失败应抛 {@see \UnexpectedValueException} 或 {@see SignatureVerificationException}。
     *
     * @return array{id: string, type: string, data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): array;
}
