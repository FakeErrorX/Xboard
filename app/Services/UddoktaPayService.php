<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\User;

class UddoktaPayService
{
    private $apiKey;
    private $baseUrl;
    private $sandboxMode;

    public function __construct($apiKey = null, $baseUrl = null, $sandboxMode = null)
    {
        $this->sandboxMode = $sandboxMode ?? config('uddoktapay.sandbox_mode', true);
        
        if ($this->sandboxMode) {
            $this->apiKey = $apiKey ?? config('uddoktapay.sandbox_api_key', '982d381360a69d419689740d9f2e26ce36fb7a50');
            $this->baseUrl = 'https://sandbox.uddoktapay.com/';
        } else {
            $this->apiKey = $apiKey ?? config('uddoktapay.live_api_key');
            $this->baseUrl = $baseUrl ?? config('uddoktapay.live_base_url');
        }

        if (!$this->apiKey) {
            throw new \Exception('UddoktaPay API key is required');
        }

        if (!$this->baseUrl) {
            throw new \Exception('UddoktaPay base URL is required');
        }

        // Ensure base URL ends with slash
        if (!str_ends_with($this->baseUrl, '/')) {
            $this->baseUrl .= '/';
        }
    }

    /**
     * Create a payment charge
     */
    public function createCharge(array $data, $useGlobal = false): array
    {
        $endpoint = $useGlobal ? 'api/checkout-global' : 'api/checkout-v2';
        
        $response = Http::withHeaders([
            'RT-UDDOKTAPAY-API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->timeout(30)->post($this->baseUrl . $endpoint, $data);

        if ($response->successful()) {
            $responseData = $response->json();
            
            if (isset($responseData['payment_url'])) {
                return [
                    'success' => true,
                    'payment_url' => $responseData['payment_url'],
                    'message' => $responseData['message'] ?? 'Payment URL generated'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment URL not found in response',
                    'data' => $responseData
                ];
            }
        } else {
            $errorData = $response->json();
            return [
                'success' => false,
                'message' => $errorData['message'] ?? 'API request failed',
                'status_code' => $response->status()
            ];
        }
    }

    /**
     * Verify a payment by invoice ID
     */
    public function verifyPayment(string $invoiceId): array
    {
        $response = Http::withHeaders([
            'RT-UDDOKTAPAY-API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->timeout(30)->post($this->baseUrl . 'api/verify-payment', [
            'invoice_id' => $invoiceId
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            return [
                'success' => true,
                'status' => $data['status'] ?? 'UNKNOWN',
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'sender_number' => $data['sender_number'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'full_data' => $data
            ];
        } else {
            $errorData = $response->json();
            return [
                'success' => false,
                'message' => $errorData['message'] ?? 'Verification failed',
                'status_code' => $response->status()
            ];
        }
    }

    /**
     * Validate webhook authenticity
     */
    public function validateWebhook(array $headers, array $payload): bool
    {
        $headerApiKey = $headers['RT-UDDOKTAPAY-API-KEY'] ?? 
                       $headers['rt-uddoktapay-api-key'] ?? 
                       $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ?? null;

        if (!$headerApiKey || $headerApiKey !== $this->apiKey) {
            Log::error('UddoktaPay: Webhook validation failed - API key mismatch', [
                'expected_prefix' => substr($this->apiKey, 0, 8) . '...',
                'received_prefix' => $headerApiKey ? substr($headerApiKey, 0, 8) . '...' : 'null'
            ]);
            return false;
        }

        return true;
    }

    /**
     * Process webhook payload
     */
    public function processWebhook(array $payload): array
    {
        $requiredFields = ['invoice_id', 'status', 'metadata'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ];
            }
        }

        $metadata = $payload['metadata'];
        $tradeNo = $metadata['trade_no'] ?? null;

        if (!$tradeNo) {
            return [
                'success' => false,
                'message' => 'Missing trade_no in metadata'
            ];
        }

        // Verify the order exists
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }

        // Check if payment is already processed
        if ($order->status !== Order::STATUS_PENDING) {
            return [
                'success' => true,
                'message' => 'Order already processed',
                'already_processed' => true
            ];
        }

        return [
            'success' => true,
            'order' => $order,
            'invoice_id' => $payload['invoice_id'],
            'status' => $payload['status'],
            'amount' => $payload['amount'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null
        ];
    }

    /**
     * Create payment for Xboard order
     */
    public function createOrderPayment(Order $order, array $options = []): array
    {
        $user = User::find($order->user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }

        $amount = number_format($order->total_amount / 100, 2, '.', '');
        $currency = $options['currency'] ?? 'BDT';
        $useGlobal = $options['use_global'] ?? false;

        // Prepare customer name
        $customerName = $user->name ?? $user->full_name ?? explode('@', $user->email)[0] ?? 'Xboard Customer';

        $paymentData = [
            'full_name' => $customerName,
            'email' => $user->email,
            'amount' => $amount,
            'metadata' => [
                'trade_no' => $order->trade_no,
                'user_id' => (string)$order->user_id,
                'order_type' => 'xboard_subscription',
                'currency' => $currency,
                'plan_id' => (string)$order->plan_id
            ],
            'redirect_url' => $options['return_url'] ?? url('/'),
            'cancel_url' => $options['cancel_url'] ?? url('/?status=cancelled'),
            'webhook_url' => $options['webhook_url'] ?? url('/api/v1/guest/payment/notify/UddoktaPay/' . config('app.key')),
            'return_type' => 'GET'
        ];

        Log::info('UddoktaPay: Creating payment for order', [
            'trade_no' => $order->trade_no,
            'amount' => $amount,
            'currency' => $currency,
            'use_global' => $useGlobal
        ]);

        return $this->createCharge($paymentData, $useGlobal);
    }

    /**
     * Get API configuration info
     */
    public function getConfig(): array
    {
        return [
            'sandbox_mode' => $this->sandboxMode,
            'base_url' => $this->baseUrl,
            'api_key_prefix' => substr($this->apiKey, 0, 8) . '...'
        ];
    }
} 