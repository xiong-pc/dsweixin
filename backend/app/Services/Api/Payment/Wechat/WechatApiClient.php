<?php

namespace App\Services\Api\Payment\Wechat;

use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;

/**
 * WechatClient 的真实实现，调用 yansongda/pay v3。
 *
 * 注意：每次调用都重新 Pay::config(...)，多租户场景下不会有配置串味。
 */
class WechatApiClient implements WechatClient
{
    public function createPayment(array $config, string $scene, array $params): array
    {
        Pay::config(['wechat' => ['default' => $config]]);

        $resp = $this->dispatchPayment($scene, $params);

        $array = $resp->all();

        // out_trade_no 是商户单号；prepay_id 是微信预下单号 —— 真实 transaction_id 在异步通知里
        return [
            'trade_no' => (string) ($params['out_trade_no'] ?? ''),
            'code_url' => (string) ($array['code_url'] ?? ''),
            'h5_url' => (string) ($array['h5_url'] ?? ''),
            'payer_params' => $array, // jsapi 场景：含 timeStamp/nonceStr/package/signType/paySign
            'raw' => $array,
        ];
    }

    public function createRefund(array $config, array $params): array
    {
        Pay::config(['wechat' => ['default' => $config]]);

        $resp = $this->dispatchRefund($params);
        $array = $resp->all();

        return [
            'refund_id' => (string) ($array['refund_id'] ?? $array['out_refund_no'] ?? ''),
            'status' => (string) ($array['status'] ?? 'PROCESSING'),
            'amount' => (int) ($array['amount']['refund'] ?? 0),
            'raw' => $array,
        ];
    }

    public function constructWebhookEvent(array $config, array $serverParams, string $body): array
    {
        Pay::config(['wechat' => ['default' => $config]]);

        /** @var array<string, mixed> $array */
        $array = $this->dispatchCallback($serverParams, $body);

        $eventType = (string) ($array['event_type'] ?? '');
        $resource = is_array($array['resource']['plaintext'] ?? null)
            ? $array['resource']['plaintext']
            : [];

        return [
            'event_type' => $eventType,
            'transaction_id' => (string) ($resource['transaction_id'] ?? ''),
            'order_no' => isset($resource['out_trade_no']) ? (string) $resource['out_trade_no'] : null,
            'amount' => (int) ($resource['amount']['total'] ?? 0),
            'currency' => (string) ($resource['amount']['currency'] ?? 'CNY'),
            'raw' => $array,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | yansongda/pay v3 SDK 派发层
    |--------------------------------------------------------------------------
    | 这些 private 方法把 magic __call 的调用集中收口，便于通过 phpstan-ignore
    | 注释整段豁免（jsapi/h5/scan/refund/callback 都不是 declared method，
    | 直接静态分析会全报 method.notFound）。
    */

    /**
     * @param  array<string, mixed>  $params
     */
    private function dispatchPayment(string $scene, array $params): Collection
    {
        // yansongda/pay v3 wechat provider 把公众号 JSAPI 称作 mp、小程序称作 mini
        /** @var Collection $resp */
        $resp = match ($scene) {
            self::SCENE_JSAPI => Pay::wechat()->mp($params),
            self::SCENE_H5 => Pay::wechat()->h5($params),
            self::SCENE_NATIVE => Pay::wechat()->scan($params),
            default => throw new \InvalidArgumentException("unsupported_wechat_scene:$scene"),
        };

        return $resp;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function dispatchRefund(array $params): Collection
    {
        /** @var Collection $resp */
        $resp = Pay::wechat()->refund($params);

        return $resp;
    }

    /**
     * @param  array<string, mixed>  $serverParams
     * @return array<string, mixed>
     */
    private function dispatchCallback(array $serverParams, string $body): array
    {
        // yansongda/pay v3 callback 签名 (?array, ?array)，但 wechat driver 兼容 string body
        /** @var Collection $resp */
        // @phpstan-ignore argument.type
        $resp = Pay::wechat()->callback($serverParams, $body);

        return $resp->all();
    }
}
