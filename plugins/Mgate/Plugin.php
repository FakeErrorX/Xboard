<?php

namespace Plugin\Mgate;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Curl\Curl;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['MGate'] = [
                    'name' => $this->getConfig('display_name', 'MGate'),
                    'icon' => $this->getConfig('icon', '🏛️'),
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
            'mgate_url' => [
                'label' => 'API Address',
                'type' => 'string',
                'required' => true,
                'description' => 'MGate payment gateway API address'
            ],
            'mgate_app_id' => [
                'label' => 'APP ID',
                'type' => 'string',
                'required' => true,
                'description' => 'MGate application identifier'
            ],
            'mgate_app_secret' => [
                'label' => 'App Secret',
                'type' => 'string',
                'required' => true,
                'description' => 'MGate application secret key'
            ],
            'mgate_source_currency' => [
                'label' => 'Source Currency',
                'type' => 'string',
                'description' => 'Default CNY, source currency type'
            ]
        ];
    }

    public function pay($order): array
    {
        $params = [
            'out_trade_no' => $order['trade_no'],
            'total_amount' => $order['total_amount'],
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url']
        ];

        if ($this->getConfig('mgate_source_currency')) {
            $params['source_currency'] = $this->getConfig('mgate_source_currency');
        }

        $params['app_id'] = $this->getConfig('mgate_app_id');
        ksort($params);
        $str = http_build_query($params) . $this->getConfig('mgate_app_secret');
        $params['sign'] = md5($str);

        $curl = new Curl();
        $curl->setUserAgent('MGate');
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $curl->post($this->getConfig('mgate_url') . '/v1/gateway/fetch', http_build_query($params));
        $result = $curl->response;

        if (!$result) {
            throw new ApiException('Network error');
        }

        if ($curl->error) {
            if (isset($result->errors)) {
                $errors = (array) $result->errors;
                throw new ApiException($errors[array_keys($errors)[0]][0]);
            }
            if (isset($result->message)) {
                throw new ApiException($result->message);
            }
            throw new ApiException('Unknown error');
        }

        $curl->close();

        if (!isset($result->data->trade_no)) {
            throw new ApiException('API request failed');
        }

        return [
            'type' => 1,
            'data' => $result->data->pay_url
        ];
    }

    public function notify($params): array|bool
    {
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        reset($params);
        $str = http_build_query($params) . $this->getConfig('mgate_app_secret');

        if ($sign !== md5($str)) {
            return false;
        }

        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no']
        ];
    }
}