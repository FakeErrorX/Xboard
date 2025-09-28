<?php

namespace Plugin\Epay;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['EPay'] = [
                    'name' => $this->getConfig('display_name', 'Easy Payment'),
                    'icon' => $this->getConfig('icon', '💳'),
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
            'url' => [
                'label' => 'Payment Gateway URL',
                'type' => 'string',
                'required' => true,
                'description' => 'Please enter the complete payment gateway address, including protocol (http or https)'
            ],
            'pid' => [
                'label' => 'Merchant ID',
                'type' => 'string',
                'description' => 'Please enter the merchant ID',
                'required' => true
            ],
            'key' => [
                'label' => 'Communication Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Please enter the communication key'
            ],
            'type' => [
                'label' => 'Payment Type',
                'type' => 'string',
                'description' => 'Payment type, such as: alipay, wxpay, qqpay, etc., can be customized'
            ],
        ];
    }

    public function pay($order): array
    {
        $params = [
            'money' => $order['total_amount'] / 100,
            'name' => $order['trade_no'],
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url'],
            'out_trade_no' => $order['trade_no'],
            'pid' => $this->getConfig('pid')
        ];

        if ($paymentType = $this->getConfig('type')) {
            $params['type'] = $paymentType;
        }

        ksort($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->getConfig('key');
        $params['sign'] = md5($str);
        $params['sign_type'] = 'MD5';

        return [
            'type' => 1,
            'data' => $this->getConfig('url') . '/submit.php?' . http_build_query($params)
        ];
    }

    public function notify($params): array|bool
    {
        $sign = $params['sign'];
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->getConfig('key');

        if ($sign !== md5($str)) {
            return false;
        }

        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no']
        ];
    }
}