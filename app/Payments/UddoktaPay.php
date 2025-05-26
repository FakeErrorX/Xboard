<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        
        // Prepare payload according to UddoktaPay API requirements
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
            'return_type' => 'GET' // Explicitly set return type to GET to avoid issues
        ];

        try {
            // Log the request for debugging
            Log::info('UddoktaPay payment request', [
                'url' => "{$baseUrl}/api/checkout-v2",
                'payload' => $payload
            ]);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
            ])->post("{$baseUrl}/api/checkout-v2", $payload);

            // Log the response for debugging
            Log::info('UddoktaPay payment response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            
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
            Log::error('UddoktaPay payment error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApiException('UddoktaPay Error: ' . $e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        // Log the incoming webhook data
        Log::info('UddoktaPay webhook received', [
            'params' => $params,
            'request' => request()->all(),
            'content' => request()->getContent()
        ]);
        
        // If webhook data is coming directly from the request body
        if (empty($params) && request()->getContent()) {
            $params = json_decode(request()->getContent(), true) ?? [];
        }
        
        // Handle GET request parameters - UddoktaPay might send as query params
        if (empty($params) && request()->query('invoice_id')) {
            $params['invoice_id'] = request()->query('invoice_id');
        }

        // Validate the webhook by verifying invoice_id
        if (!isset($params['invoice_id'])) {
            Log::warning('UddoktaPay webhook missing invoice_id');
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

            // Log the verification response
            Log::info('UddoktaPay payment verification response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            $paymentData = $response->json();
            
            // Check if payment was successful
            if (!$response->successful()) {
                Log::warning('UddoktaPay API verification failed', [
                    'status' => $response->status(),
                    'response' => $paymentData
                ]);
                return false;
            }
            
            // Check if metadata exists
            if (!isset($paymentData['metadata']) || !isset($paymentData['metadata']['order_id'])) {
                Log::warning('UddoktaPay missing metadata or order_id');
                return false;
            }

            // Check payment status
            if ($paymentData['status'] !== 'COMPLETED') {
                Log::warning('UddoktaPay payment not completed', ['status' => $paymentData['status']]);
                return false;
            }
            
            return [
                'trade_no' => $paymentData['metadata']['order_id'],
                'callback_no' => $paymentData['transaction_id'] ?? $params['invoice_id'],
                'custom_result' => json_encode(['status' => 'success'])
            ];
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay notification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
