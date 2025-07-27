<?php

namespace Plugin\UddoktaPay;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    protected $apiBaseUrl = 'https://api.uddoktapay.com/api';
    
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['UddoktaPay'] = [
                    'name' => $this->getConfig('display_name', 'UddoktaPay'),
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
            'enabled' => [
                'label' => '启用',
                'type' => 'switch',
                'default' => true
            ],
            'display_name' => [
                'label' => '显示名称',
                'type' => 'string',
                'default' => 'UddoktaPay'
            ],
            'icon' => [
                'label' => '图标',
                'type' => 'string',
                'default' => '💳'
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Your UddoktaPay API key'
            ],
            'sandbox_mode' => [
                'label' => 'Sandbox Mode',
                'type' => 'select',
                'options' => [
                    ['value' => 'true', 'label' => 'Enabled (Testing)'],
                    ['value' => 'false', 'label' => 'Disabled (Production)']
                ],
                'default' => 'true'
            ],
            'currency' => [
                'label' => 'Currency',
                'type' => 'string',
                'default' => 'BDT',
                'description' => 'Payment currency (e.g., BDT, USD)'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $apiKey = $this->getConfig('api_key');
            $sandboxMode = $this->getConfig('sandbox_mode', 'true') === 'true';
            $currency = $this->getConfig('currency', 'BDT');
            
            // Get customer information
            $customerEmail = $this->getCustomerEmail($order['user_id']);
            $customerName = $this->getCustomerName($order['user_id']);
            
            // Prepare payment data according to UddoktaPay API
            $paymentData = [
                'full_name' => $customerName,
                'email' => $customerEmail,
                'amount' => (string)($order['total_amount'] / 100), // Convert from cents to string
                'metadata' => [
                    'trade_no' => $order['trade_no'],
                    'user_id' => (string)$order['user_id'],
                    'order_type' => 'xboard_subscription'
                ],
                'redirect_url' => $order['return_url'],
                'cancel_url' => $order['return_url'] . '?status=cancelled',
                'webhook_url' => $order['notify_url'], // This will be the standard notification URL
                'return_type' => 'GET'
            ];

            // Determine API base URL based on sandbox mode
            // Using official UddoktaPay sandbox URL from API Information
            $apiBaseUrl = $sandboxMode ? 'https://sandbox.uddoktapay.com/' : 'https://pay.uddoktapay.com/';

            // Make API request to create payment
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($apiBaseUrl . 'api/checkout-v2', $paymentData);

            if (!$response->successful()) {
                Log::error('UddoktaPay payment creation failed', [
                    'response' => $response->json(),
                    'order' => $order,
                    'status_code' => $response->status(),
                    'sandbox_mode' => $sandboxMode,
                    'api_base_url' => $apiBaseUrl
                ]);
                throw new \Exception('Failed to create payment: ' . $response->body());
            }

            $paymentResponse = $response->json();
            
            if (!$paymentResponse['status']) {
                Log::error('UddoktaPay payment creation failed', [
                    'message' => $paymentResponse['message'] ?? 'Unknown error',
                    'order' => $order
                ]);
                throw new \Exception('Payment creation failed: ' . ($paymentResponse['message'] ?? 'Unknown error'));
            }
            
            Log::info('UddoktaPay payment created successfully', [
                'payment_url' => $paymentResponse['payment_url'] ?? null,
                'trade_no' => $order['trade_no'],
                'sandbox_mode' => $sandboxMode,
                'api_base_url' => $apiBaseUrl
            ]);
            
            // Return payment URL for redirect
            return [
                'type' => 1, // Redirect type
                'data' => $paymentResponse['payment_url']
            ];

        } catch (\Exception $e) {
            Log::error('UddoktaPay payment error', [
                'error' => $e->getMessage(),
                'order' => $order,
                'sandbox_mode' => $sandboxMode ?? 'unknown'
            ]);
            throw $e;
        }
    }

    public function notify($params): array|bool
    {
        try {
            // Check if this is a webhook call (has full payload) or redirect call (has invoice_id)
            $invoiceId = null;
            $webhookData = null;
            
            // If params contain webhook data (from webhook call)
            if (isset($params['invoice_id']) && isset($params['status'])) {
                // This is a webhook call with full payload
                $webhookData = $params;
                $invoiceId = $params['invoice_id'];
                
                Log::info('UddoktaPay notify: Processing webhook data', [
                    'invoice_id' => $invoiceId,
                    'status' => $params['status'] ?? 'unknown'
                ]);
                
                // Check if payment is completed
                if ($params['status'] !== 'COMPLETED') {
                    Log::info('UddoktaPay webhook payment not completed', [
                        'invoice_id' => $invoiceId,
                        'status' => $params['status']
                    ]);
                    return false;
                }
                
                // Extract trade number from metadata
                $tradeNo = $params['metadata']['trade_no'] ?? '';
                
                if (!$tradeNo) {
                    Log::error('UddoktaPay webhook missing trade_no in metadata', [
                        'invoice_id' => $invoiceId,
                        'webhook_data' => $params
                    ]);
                    return false;
                }
                
                Log::info('UddoktaPay webhook payment verified successfully - order will be completed', [
                    'invoice_id' => $invoiceId,
                    'trade_no' => $tradeNo,
                    'amount' => $params['amount'] ?? 0,
                    'payment_method' => $params['payment_method'] ?? 'unknown'
                ]);
                
                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $invoiceId
                ];
                
            } else {
                // This is a redirect call with invoice_id parameter
                $invoiceId = $params['invoice_id'] ?? null;
                
                if (!$invoiceId) {
                    Log::error('UddoktaPay notify: Missing invoice_id', ['params' => $params]);
                    return false;
                }
                
                Log::info('UddoktaPay notify: Processing redirect with invoice_id', [
                    'invoice_id' => $invoiceId
                ]);
                
                // Verify payment using UddoktaPay Verify Payment API
                $result = $this->verifyPayment($invoiceId);
                
                if (!$result) {
                    Log::error('UddoktaPay payment verification failed', ['invoice_id' => $invoiceId]);
                    return false;
                }
                
                // Extract trade number from metadata
                $tradeNo = $result['metadata']['trade_no'] ?? '';
                
                if (!$tradeNo) {
                    Log::error('UddoktaPay payment missing trade_no in metadata', [
                        'invoice_id' => $invoiceId,
                        'result' => $result
                    ]);
                    return false;
                }
                
                // Check if payment is completed
                if ($result['status'] !== 'COMPLETED') {
                    Log::info('UddoktaPay payment not completed', [
                        'invoice_id' => $invoiceId,
                        'status' => $result['status']
                    ]);
                    return false;
                }
                
                Log::info('UddoktaPay payment verified successfully - order will be completed', [
                    'invoice_id' => $invoiceId,
                    'trade_no' => $tradeNo,
                    'amount' => $result['amount'] ?? 0,
                    'payment_method' => $result['payment_method'] ?? 'unknown'
                ]);
                
                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $invoiceId
                ];
            }

        } catch (\Exception $e) {
            Log::error('UddoktaPay notify processing error', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            return false;
        }
    }

    /**
     * Verify payment via UddoktaPay Verify Payment API
     */
    public function verifyPayment($invoiceId): array|bool
    {
        try {
            $apiKey = $this->getConfig('api_key');
            $sandboxMode = $this->getConfig('sandbox_mode', 'true') === 'true';
            
            // Determine API base URL based on sandbox mode
            // Using official UddoktaPay sandbox URL from API Information
            $apiBaseUrl = $sandboxMode ? 'https://sandbox.uddoktapay.com/' : 'https://pay.uddoktapay.com/';
            
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($apiBaseUrl . 'api/verify-payment', [
                'invoice_id' => $invoiceId
            ]);

            if (!$response->successful()) {
                Log::error('UddoktaPay payment verification API failed', [
                    'invoice_id' => $invoiceId,
                    'response' => $response->json(),
                    'status_code' => $response->status(),
                    'sandbox_mode' => $sandboxMode,
                    'api_base_url' => $apiBaseUrl
                ]);
                return false;
            }

            $paymentData = $response->json();
            
            // Check if API returned an error
            if (isset($paymentData['status']) && $paymentData['status'] === 'ERROR') {
                Log::error('UddoktaPay payment verification error', [
                    'invoice_id' => $invoiceId,
                    'message' => $paymentData['message'] ?? 'Unknown error',
                    'sandbox_mode' => $sandboxMode
                ]);
                return false;
            }

            Log::info('UddoktaPay payment verification successful', [
                'invoice_id' => $invoiceId,
                'status' => $paymentData['status'] ?? 'unknown',
                'amount' => $paymentData['amount'] ?? 0,
                'sandbox_mode' => $sandboxMode,
                'api_base_url' => $apiBaseUrl
            ]);

            return $paymentData;

        } catch (\Exception $e) {
            Log::error('UddoktaPay API verification error', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
                'sandbox_mode' => $sandboxMode ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Get customer email from user ID
     */
    private function getCustomerEmail($userId): string
    {
        $user = \App\Models\User::find($userId);
        return $user ? $user->email : 'customer@example.com';
    }

    /**
     * Get customer name from user ID
     */
    private function getCustomerName($userId): string
    {
        $user = \App\Models\User::find($userId);
        return $user ? ($user->name ?? $user->username ?? 'Customer') : 'Customer';
    }
} 