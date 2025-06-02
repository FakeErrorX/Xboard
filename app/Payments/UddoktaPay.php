<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use GuzzleHttp\Client;

class UddoktaPay implements PaymentInterface
{
    protected $config;
    protected $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new Client();
    }

    public function form(): array
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'description' => 'UddoktaPay API Key from Dashboard',
                'type' => 'input',
            ],
            'base_url' => [
                'label' => 'Base URL',
                'description' => 'UddoktaPay API Base URL (e.g., https://pay.your-domain.com)',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order): array
    {
        if (empty($this->config['api_key'])) {
            throw new ApiException('UddoktaPay API Key is required');
        }

        if (empty($this->config['base_url'])) {
            throw new ApiException('UddoktaPay Base URL is required');
        }

        try {
            $amount = sprintf('%.2f', $order['total_amount'] / 100);
            
            $params = [
                'full_name' => 'User_' . $order['user_id'],
                'email' => 'user_' . $order['user_id'] . '@example.com',
                'amount' => $amount,
                'metadata' => [
                    'trade_no' => $order['trade_no'],
                    'user_id' => $order['user_id']
                ],
                'redirect_url' => $order['return_url'],
                'cancel_url' => $order['return_url'],
                'webhook_url' => $order['notify_url'],
                'return_type' => 'GET'
            ];

            $response = $this->client->request('POST', rtrim($this->config['base_url'], '/') . '/api/checkout-v2', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ],
                'json' => $params
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['payment_url'])) {
                throw new ApiException('UddoktaPay payment creation failed');
            }

            return [
                'type' => 1, // Redirect to URL
                'data' => $result['payment_url']
            ];
        } catch (\Exception $e) {
            \Log::error('UddoktaPay payment error: ' . $e->getMessage());
            throw new ApiException('UddoktaPay payment error: ' . $e->getMessage());
        }
    }

    public function notify($params)
    {
        try {
            $payload = request()->getContent();
            $data = json_decode($payload, true);

            if (!$data) {
                throw new ApiException('Invalid webhook data');
            }

            // Verify the payment status
            if (!isset($data['status']) || $data['status'] !== 'COMPLETED') {
                return false;
            }

            // Extract metadata
            if (!isset($data['metadata']) || !isset($data['metadata']['trade_no'])) {
                throw new ApiException('Invalid metadata in webhook');
            }

            $tradeNo = $data['metadata']['trade_no'];
            $invoiceId = $data['invoice_id'] ?? null;

            if (!$invoiceId) {
                throw new ApiException('Missing invoice ID in webhook');
            }

            // Verify payment with API
            $this->verifyPayment($invoiceId);

            return [
                'trade_no' => $tradeNo,
                'callback_no' => $invoiceId
            ];
        } catch (\Exception $e) {
            \Log::error('UddoktaPay webhook error: ' . $e->getMessage());
            return false;
        }
    }

    protected function verifyPayment($invoiceId)
    {
        $response = $this->client->request('POST', rtrim($this->config['base_url'], '/') . '/api/verify-payment', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
            ],
            'json' => [
                'invoice_id' => $invoiceId
            ]
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (!isset($result['status']) || $result['status'] !== 'COMPLETED') {
            throw new ApiException('Payment not completed');
        }

        return $result;
    }
}
