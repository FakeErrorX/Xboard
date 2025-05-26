<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;

class UddoktaPay implements PaymentInterface
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'base_url' => [
                'label' => 'Base URL',
                'description' => 'UddoktaPay API URL (e.g. https://pay.yourdomain.com or https://sandbox.uddoktapay.com)',
                'type' => 'input',
            ],
            'api_key' => [
                'label' => 'API Key',
                'description' => 'Your UddoktaPay API Key',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order): array
    {
        // Ensure the base_url doesn't end with a slash
        $baseUrl = rtrim($this->config['base_url'], '/');
        
        $payload = [
            'full_name' => 'Customer',
            'email' => 'customer@example.com',
            'amount' => $order['total_amount'] / 100, // Convert from cents to whole currency unit
            'metadata' => [
                'order_id' => $order['trade_no'],
            ],
            'redirect_url' => $order['return_url'],
            'cancel_url' => $order['return_url'],
            'webhook_url' => $order['notify_url'],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
            ])->post("{$baseUrl}/api/checkout-v2", $payload);

            $responseData = $response->json();
            
            if (!$response->successful() || !isset($responseData['payment_url'])) {
                $errorMessage = $responseData['message'] ?? 'Payment initialization failed';
                throw new ApiException($errorMessage);
            }
            
            return [
                'type' => 1, // URL redirect
                'data' => $responseData['payment_url']
            ];
            
        } catch (\Exception $e) {
            throw new ApiException('UddoktaPay Error: ' . $e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        // If webhook data is coming directly from the request body
        if (empty($params) && request()->getContent()) {
            $params = json_decode(request()->getContent(), true) ?? [];
        }

        // Validate the webhook by verifying invoice_id
        if (!isset($params['invoice_id'])) {
            return false;
        }

        try {
            // Verify payment status with UddoktaPay API
            $baseUrl = rtrim($this->config['base_url'], '/');
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
            ])->post("{$baseUrl}/api/verify-payment", [
                'invoice_id' => $params['invoice_id']
            ]);

            $paymentData = $response->json();
            
            // Check if payment was successful
            if (!$response->successful() || !isset($paymentData['metadata']['order_id'])) {
                return false;
            }

            // Check payment status
            if ($paymentData['status'] !== 'COMPLETED') {
                return false;
            }
            
            return [
                'trade_no' => $paymentData['metadata']['order_id'],
                'callback_no' => $paymentData['transaction_id'],
                'custom_result' => json_encode(['status' => 'success'])
            ];
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
