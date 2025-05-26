<?php

namespace App\Payments;

use GuzzleHttp\Client;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;

class UddoktaPay implements PaymentInterface
{
    private $config;
    private $client;
    private $apiHost;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new Client();
        $this->apiHost = $this->config['base_url'] ?? 'https://pay.uddoktapay.com';
    }

    public function form()
    {
        return [
            'base_url' => [
                'label' => 'API URL',
                'description' => 'UddoktaPay API URL (e.g. https://pay.your-domain.com)',
                'type' => 'input',
            ],
            'api_key' => [
                'label' => 'API Key',
                'description' => 'Collect API KEY from UddoktaPay Dashboard',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order)
    {
        try {
            $response = $this->client->request('POST', "{$this->apiHost}/api/checkout-v2", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ],
                'json' => [
                    'full_name' => $order['email'] ?? 'Customer',
                    'email' => $order['email'] ?? 'customer@example.com',
                    'amount' => sprintf('%.2f', $order['total_amount'] / 100),
                    'metadata' => [
                        'order_id' => $order['trade_no']
                    ],
                    'redirect_url' => $order['return_url'],
                    'cancel_url' => $order['return_url'],
                    'webhook_url' => $order['notify_url'] ?? '',
                    'return_type' => 'GET'
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            if (isset($result['payment_url'])) {
                return [
                    'type' => 1, // 0:qrcode 1:url
                    'data' => $result['payment_url']
                ];
            } else {
                throw new ApiException('UddoktaPay payment URL not found in response');
            }
        } catch (\Exception $e) {
            throw new ApiException('UddoktaPay Error: ' . $e->getMessage());
        }
    }

    public function notify($params)
    {
        $invoiceId = $params['invoice_id'] ?? request()->input('invoice_id');
        
        if (empty($invoiceId)) {
            throw new ApiException('Invoice ID not found');
        }

        try {
            $response = $this->client->request('POST', "{$this->apiHost}/api/verify-payment", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ],
                'json' => [
                    'invoice_id' => $invoiceId
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            if (isset($result['status']) && $result['status'] === 'COMPLETED') {
                // Extract trade_no from metadata
                $tradeNo = $result['metadata']['order_id'] ?? '';
                
                if (empty($tradeNo)) {
                    throw new ApiException('Trade number not found in payment response');
                }

                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $invoiceId
                ];
            }
        } catch (\Exception $e) {
            throw new ApiException('UddoktaPay Verification Error: ' . $e->getMessage());
        }

        return false;
    }
}
