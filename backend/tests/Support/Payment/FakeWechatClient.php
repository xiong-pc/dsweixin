<?php

namespace Tests\Support\Payment;

use App\Services\Api\Payment\Wechat\WechatClient;

/**
 * 测试用 WechatClient（不调真 yansongda/pay）。
 *
 * 同 FakeStripeClient 思路：记录调用 + 注入预设响应 + 可控签名失败开关。
 */
class FakeWechatClient implements WechatClient
{
    /** @var array<string, mixed>|null */
    public ?array $nextPayment = null;

    /** @var array<string, mixed>|null */
    public ?array $nextRefund = null;

    /** @var array<string, mixed>|null */
    public ?array $nextEvent = null;

    public bool $shouldFailWebhook = false;

    /** @var array<int, array{config: array<string, mixed>, scene: string, params: array<string, mixed>}> */
    public array $paymentCalls = [];

    /** @var array<int, array{config: array<string, mixed>, params: array<string, mixed>}> */
    public array $refundCalls = [];

    /** @var array<int, array{config: array<string, mixed>, server: array<string, mixed>, body: string}> */
    public array $webhookCalls = [];

    public function createPayment(array $config, string $scene, array $params): array
    {
        $this->paymentCalls[] = ['config' => $config, 'scene' => $scene, 'params' => $params];

        return $this->nextPayment ?? [
            'trade_no' => (string) ($params['out_trade_no'] ?? ''),
            'code_url' => $scene === WechatClient::SCENE_NATIVE ? 'weixin://wxpay/bizpayurl?pr=fake' : '',
            'h5_url' => $scene === WechatClient::SCENE_H5 ? 'https://wx.tenpay.com/cgi-bin/mmpayweb/mwebpay?prepay_id=fake' : '',
            'payer_params' => $scene === WechatClient::SCENE_JSAPI ? [
                'timeStamp' => '1700000000',
                'nonceStr' => 'fakeNonce',
                'package' => 'prepay_id=wx_fake',
                'signType' => 'RSA',
                'paySign' => 'FAKE_SIGN',
            ] : [],
            'raw' => ['mocked' => true],
        ];
    }

    public function createRefund(array $config, array $params): array
    {
        $this->refundCalls[] = ['config' => $config, 'params' => $params];

        return $this->nextRefund ?? [
            'refund_id' => '50000000'.uniqid(),
            'status' => 'SUCCESS',
            'amount' => (int) ($params['amount']['refund'] ?? 0),
            'raw' => ['mocked' => true],
        ];
    }

    public function constructWebhookEvent(array $config, array $serverParams, string $body): array
    {
        $this->webhookCalls[] = ['config' => $config, 'server' => $serverParams, 'body' => $body];

        if ($this->shouldFailWebhook) {
            throw new \RuntimeException('fake_invalid_wechat_signature');
        }

        return $this->nextEvent ?? [
            'event_type' => 'TRANSACTION.SUCCESS',
            'transaction_id' => '4200001234567890123456',
            'order_no' => null,
            'amount' => 0,
            'currency' => 'CNY',
            'raw' => ['mocked' => true],
        ];
    }
}
