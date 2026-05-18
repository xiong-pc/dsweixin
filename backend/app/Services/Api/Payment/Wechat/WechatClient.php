<?php

namespace App\Services\Api\Payment\Wechat;

use App\Services\Api\Payment\Stripe\StripeClient;

/**
 * 微信支付 SDK（yansongda/pay）的薄包装契约。
 *
 * 抽象目的同 {@see StripeClient}：
 * 让 WechatDriver 不静态依赖 Pay::wechat()，测试可注入 fake 实现。
 *
 * scene 取值（沿用微信行业术语，内部映射到 yansongda v3 方法名）：
 *   - 'jsapi'  公众号 JSAPI（→ Pay::wechat()->mp）；小程序后续可再加 SCENE_MINI
 *   - 'h5'     手机 H5 浏览器（→ Pay::wechat()->h5）
 *   - 'native' PC 扫码（→ Pay::wechat()->scan）
 */
interface WechatClient
{
    public const SCENE_JSAPI = 'jsapi';

    public const SCENE_H5 = 'h5';

    public const SCENE_NATIVE = 'native';

    /**
     * 统一下单。
     *
     * @param  array<string, mixed>  $config  yansongda/pay wechat 配置（mch_id/secret_cert/app_id/mch_secret_key 等）
     * @param  array<string, mixed>  $params  统一下单参数（out_trade_no/description/amount/payer/scene_info ...）
     * @return array{trade_no: string, code_url: string, h5_url: string, payer_params: array<string, mixed>, raw: array<string, mixed>}
     */
    public function createPayment(array $config, string $scene, array $params): array;

    /**
     * 发起退款。
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params  out_trade_no + out_refund_no + amount
     * @return array{refund_id: string, status: string, amount: int, raw: array<string, mixed>}
     */
    public function createRefund(array $config, array $params): array;

    /**
     * 验签 + 解密 V3 异步通知。
     *
     * 失败应抛 \Yansongda\Pay\Exception\InvalidParamsException 或类似异常。
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $serverParams  HTTP 头（Wechatpay-Signature/Timestamp/Serial/Nonce）
     * @param  string  $body  原始 JSON body
     * @return array{event_type: string, transaction_id: string, order_no: ?string, amount: int, currency: string, raw: array<string, mixed>}
     */
    public function constructWebhookEvent(array $config, array $serverParams, string $body): array;
}
