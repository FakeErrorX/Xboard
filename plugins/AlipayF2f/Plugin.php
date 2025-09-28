<?php

namespace Plugin\AlipayF2f;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Plugin\AlipayF2f\library\AlipayF2F;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['AlipayF2F'] = [
                    'name' => $this->getConfig('display_name', 'Alipay Face to Face'),
                    'icon' => $this->getConfig('icon', '💙'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'app_id' => [
                'label' => 'Alipay APP ID',
                'type' => 'string',
                'required' => true,
                'description' => 'Alipay Open Platform Application APP ID'
            ],
            'private_key' => [
                'label' => 'Alipay Private Key',
                'type' => 'text',
                'required' => true,
                'description' => 'Application private key for signing'
            ],
            'public_key' => [
                'label' => 'Alipay Public Key',
                'type' => 'text',
                'required' => true,
                'description' => 'Alipay public key for signature verification'
            ],
            'product_name' => [
                'label' => 'Custom Product Name',
                'type' => 'string',
                'description' => 'Will be displayed in Alipay billing'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $gateway = new AlipayF2F();
            $gateway->setMethod('alipay.trade.precreate');
            $gateway->setAppId($this->getConfig('app_id'));
            $gateway->setPrivateKey($this->getConfig('private_key'));
            $gateway->setAlipayPublicKey($this->getConfig('public_key'));
            $gateway->setNotifyUrl($order['notify_url']);
            $gateway->setBizContent([
                'subject' => $this->getConfig('product_name') ?? (admin_setting('app_name', 'XBoard') . ' - Subscription'),
                'out_trade_no' => $order['trade_no'],
                'total_amount' => $order['total_amount'] / 100
            ]);
            $gateway->send();
            return [
                'type' => 0,
                'data' => $gateway->getQrCodeUrl()
            ];
        } catch (\Exception $e) {
            Log::error($e);
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        if ($params['trade_status'] !== 'TRADE_SUCCESS')
            return false;

        $gateway = new AlipayF2F();
        $gateway->setAppId($this->getConfig('app_id'));
        $gateway->setPrivateKey($this->getConfig('private_key'));
        $gateway->setAlipayPublicKey($this->getConfig('public_key'));

        try {
            if ($gateway->verify($params)) {
                return [
                    'trade_no' => $params['out_trade_no'],
                    'callback_no' => $params['trade_no']
                ];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}