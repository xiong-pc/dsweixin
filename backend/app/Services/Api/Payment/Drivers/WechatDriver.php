<?php

namespace App\Services\Api\Payment\Drivers;

use App\Contracts\Payment\PaymentResult;
use App\Contracts\Payment\RefundResult;
use App\Contracts\Payment\WebhookResult;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Services\Api\Payment\Wechat\WechatClient;
use Illuminate\Http\Request;

/**
 * 微信支付驱动（V3 API，jsapi / h5 / native 三场景）。
 *
 * config（PaymentMethod.config 字段）：
 *   - mch_id              商户号
 *   - app_id              公众号 / 小程序 / APP / 服务商 appid
 *   - mch_secret_cert     商户私钥路径
 *   - mch_public_cert_path  商户证书（serial_no 索引）
 *   - mch_secret_key      APIv3 密钥
 *   - mode                normal | sandbox | service（默认 normal）
 *   - scene               jsapi | h5 | native（决定 charge 走哪个分支）
 *   - notify_url          异步回调 URL（必填）
 */
class WechatDriver extends AbstractPaymentDriver
{
    public function __construct(
        PaymentMethod $method,
        private readonly WechatClient $client,
    ) {
        parent::__construct($method);
    }

    public function charge(Order $order, array $params = []): PaymentResult
    {
        $config = $this->buildConfig();
        $scene = (string) ($params['scene'] ?? $this->config('scene', WechatClient::SCENE_H5));

        $unifyParams = [
            'out_trade_no' => (string) $order->order_no,
            'description' => (string) ($params['description'] ?? 'Order '.$order->order_no),
            'notify_url' => (string) ($params['notify_url'] ?? $this->config('notify_url', '')),
            'amount' => [
                'total' => $this->toFen((float) $order->total),
                'currency' => strtoupper((string) ($order->currency ?: 'CNY')),
            ],
            'attach' => 'order_no='.$order->order_no.';order_id='.$order->id,
        ];

        if ($unifyParams['notify_url'] === '') {
            throw new BusinessException('api.wechat_notify_url_missing');
        }

        $unifyParams = array_merge($unifyParams, $this->sceneSpecificParams($scene, $params));

        $resp = $this->client->createPayment($config, $scene, $unifyParams);

        return new PaymentResult(
            success: true,
            transactionId: $resp['trade_no'],  // 用 out_trade_no 当临时 tx，真正的 transaction_id 在 webhook 拿
            payUrl: $this->resolvePayUrl($scene, $resp),
            payParams: $this->resolvePayParams($scene, $resp),
            raw: $resp['raw'],
        );
    }

    public function refund(OrderPayment $payment, float $amount): RefundResult
    {
        $config = $this->buildConfig();
        $raw = is_array($payment->raw_response) ? $payment->raw_response : [];

        $orderNo = (string) ($raw['order_no'] ?? '');
        // 退款单号取本次随机串；amount 用最小货币单位
        $outRefundNo = 'RF'.uniqid();

        $resp = $this->client->createRefund($config, [
            'out_trade_no' => $orderNo !== '' ? $orderNo : (string) $payment->transaction_id,
            'out_refund_no' => $outRefundNo,
            'amount' => [
                'refund' => $this->toFen($amount),
                'total' => $this->toFen((float) $payment->amount),
                'currency' => strtoupper((string) $payment->currency),
            ],
        ]);

        $success = in_array($resp['status'], ['SUCCESS', 'PROCESSING'], true);

        return new RefundResult(
            success: $success,
            refundId: $resp['refund_id'],
            amount: $amount,
            message: $success ? '' : $resp['status'],
            raw: $resp['raw'],
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $config = $this->buildConfig();
        // yansongda/pay v3 callback 需要 server params + raw body
        $serverParams = $request->server->all();
        $body = (string) $request->getContent();

        try {
            $event = $this->client->constructWebhookEvent($config, $serverParams, $body);
        } catch (\Throwable $e) {
            return new WebhookResult(
                success: false,
                eventType: WebhookResult::EVENT_UNKNOWN,
                message: $e->getMessage(),
                raw: ['reason' => 'invalid_signature'],
            );
        }

        $type = $event['event_type'];
        $eventTypeMapped = match (true) {
            $type === 'TRANSACTION.SUCCESS' => WebhookResult::EVENT_PAYMENT_SUCCESS,
            $type === 'REFUND.SUCCESS' => WebhookResult::EVENT_REFUND_COMPLETED,
            str_contains($type, 'REFUND') => WebhookResult::EVENT_REFUND_COMPLETED,
            str_contains($type, 'FAIL') => WebhookResult::EVENT_PAYMENT_FAILED,
            default => WebhookResult::EVENT_UNKNOWN,
        };

        return new WebhookResult(
            success: true,
            eventType: $eventTypeMapped,
            transactionId: $event['transaction_id'] !== '' ? $event['transaction_id'] : null,
            orderNo: $event['order_no'],
            amount: $this->fromFen($event['amount']),
            raw: $event['raw'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        $required = ['mch_id', 'app_id', 'mch_secret_key'];
        foreach ($required as $key) {
            if ((string) $this->config($key, '') === '') {
                throw new BusinessException('api.payment_driver_unavailable');
            }
        }

        return [
            'mch_id' => (string) $this->config('mch_id'),
            'app_id' => (string) $this->config('app_id'),
            'mch_secret_cert' => (string) $this->config('mch_secret_cert', ''),
            'mch_public_cert_path' => (array) $this->config('mch_public_cert_path', []),
            'mch_secret_key' => (string) $this->config('mch_secret_key'),
            'mode' => (string) $this->config('mode', 'normal'),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function sceneSpecificParams(string $scene, array $params): array
    {
        return match ($scene) {
            WechatClient::SCENE_JSAPI => [
                'payer' => ['openid' => (string) ($params['openid'] ?? '')],
            ],
            WechatClient::SCENE_H5 => [
                'scene_info' => [
                    'payer_client_ip' => (string) ($params['client_ip'] ?? '127.0.0.1'),
                    'h5_info' => ['type' => 'Wap'],
                ],
            ],
            WechatClient::SCENE_NATIVE => [],
            default => throw new BusinessException('api.wechat_unsupported_scene'),
        };
    }

    /**
     * @param  array{trade_no: string, code_url: string, h5_url: string, payer_params: array<string, mixed>, raw: array<string, mixed>}  $resp
     */
    private function resolvePayUrl(string $scene, array $resp): string
    {
        return match ($scene) {
            WechatClient::SCENE_H5 => $resp['h5_url'],
            WechatClient::SCENE_NATIVE => $resp['code_url'],
            default => '',
        };
    }

    /**
     * @param  array{trade_no: string, code_url: string, h5_url: string, payer_params: array<string, mixed>, raw: array<string, mixed>}  $resp
     */
    private function resolvePayParams(string $scene, array $resp): string
    {
        if ($scene !== WechatClient::SCENE_JSAPI) {
            return '';
        }

        // jsapi：把 timeStamp/nonceStr/package/signType/paySign 序列化给前端 wx.chooseWXPay
        return (string) json_encode($resp['payer_params'], JSON_UNESCAPED_UNICODE);
    }

    private function toFen(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromFen(int $fen): float
    {
        return round($fen / 100, 2);
    }
}
