<?php

namespace Plugin\Uddoktapay;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['UddoktaPay'] = [
                    'name' => 'UddoktaPay',
                    'icon' => '💳',
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
                'default' => false
            ],
            'sandbox_mode' => [
                'label' => 'Sandbox Mode',
                'type' => 'switch',
                'default' => false,
                'description' => 'Enable for testing, disable for live payments'
            ],
            'api_key' => [
                'label' => 'Sandbox API Key',
                'type' => 'string',
                'required' => false,
                'default' => '982d381360a69d419689740d9f2e26ce36fb7a50',
                'description' => 'Your UddoktaPay Sandbox API key (required when sandbox is enabled)',
                'show_when' => [
                    'sandbox_mode' => true
                ]
            ],
            'live_base_url' => [
                'label' => 'Live Base URL',
                'type' => 'string',
                'required' => true,
                'description' => 'Your UddoktaPay Live API base URL (required when sandbox is disabled)',
                'show_when' => [
                    'sandbox_mode' => false
                ]
            ],
            'live_api_key' => [
                'label' => 'Live API Key',
                'type' => 'string',
                'required' => false,
                'description' => 'Your UddoktaPay Live API key (required when sandbox is disabled)',
                'show_when' => [
                    'sandbox_mode' => false
                ]
            ],
            'currency' => [
                'label' => 'Currency',
                'type' => 'select',
                'options' => [
                    ['value' => 'BDT', 'label' => 'BDT - Bangladeshi Taka'],
                    ['value' => 'USD', 'label' => 'USD - US Dollar'],
                    ['value' => 'EUR', 'label' => 'EUR - Euro'],
                    ['value' => 'GBP', 'label' => 'GBP - British Pound'],
                    ['value' => 'INR', 'label' => 'INR - Indian Rupee'],
                    ['value' => 'PKR', 'label' => 'PKR - Pakistani Rupee'],
                    ['value' => 'AED', 'label' => 'AED - UAE Dirham'],
                    ['value' => 'SAR', 'label' => 'SAR - Saudi Riyal'],
                    ['value' => 'QAR', 'label' => 'QAR - Qatari Riyal'],
                    ['value' => 'KWD', 'label' => 'KWD - Kuwaiti Dinar']
                ],
                'default' => 'BDT'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $sandboxMode = $this->getConfig('sandbox_mode', false);
            $apiKey = $sandboxMode ? $this->getConfig('api_key') : $this->getConfig('live_api_key');
            $currency = $this->getConfig('currency', 'BDT');
            
            if (!$apiKey) {
                throw new \Exception('API key is required');
            }
            
            // Get customer information
            $customerEmail = $this->getCustomerEmail($order['user_id']);
            $customerName = $this->getCustomerName($order['user_id']);
            
            // Determine API base URL based on sandbox mode
            $apiBaseUrl = $sandboxMode ? 'https://sandbox.uddoktapay.com/' : $this->getConfig('live_base_url');
            
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
                'webhook_url' => $order['notify_url'],
                'return_type' => 'GET'
            ];

            // Make API request to UddoktaPay
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($apiBaseUrl . 'api/checkout-v2', $paymentData);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['payment_url'])) {
                    return [
                        'type' => 'redirect',
                        'url' => $data['payment_url']
                    ];
                } else {
                    throw new \Exception('Payment URL not found in response');
                }
            } else {
                $error = $response->json();
                throw new \Exception('UddoktaPay API Error: ' . ($error['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay payment error: ' . $e->getMessage());
            throw new \Exception('Payment initialization failed: ' . $e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        try {
            $sandboxMode = $this->getConfig('sandbox_mode', false);
            $apiKey = $sandboxMode ? $this->getConfig('api_key') : $this->getConfig('live_api_key');
            
            if (!$apiKey) {
                Log::error('UddoktaPay: API key not configured');
                return false;
            }
            
            // Verify the webhook API key (following UddoktaPay documentation)
            $headerApiKey = isset($_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY']) ? $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] : null;
            
            if (!$headerApiKey || $headerApiKey !== $apiKey) {
                Log::error('UddoktaPay: Unauthorized webhook - API key mismatch');
                return false;
            }
            
            // Extract order information from webhook
            $tradeNo = $params['metadata']['trade_no'] ?? null;
            $userId = $params['metadata']['user_id'] ?? null;
            $status = $params['status'] ?? null;
            $amount = $params['amount'] ?? null;
            
            if (!$tradeNo || !$userId) {
                Log::error('UddoktaPay: Missing trade_no or user_id in webhook');
                return false;
            }
            
            // Verify payment status
            if ($status === 'COMPLETED') {
                // Verify payment amount
                $orderAmount = $this->getOrderAmount($tradeNo);
                if ($orderAmount && $amount == $orderAmount) {
                    return [
                        'trade_no' => $tradeNo,
                        'user_id' => $userId,
                        'status' => 'success',
                        'amount' => $amount
                    ];
                } else {
                    Log::error('UddoktaPay: Amount mismatch', [
                        'expected' => $orderAmount,
                        'received' => $amount
                    ]);
                    return false;
                }
            } else {
                Log::info('UddoktaPay: Payment not completed', ['status' => $status]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay notify error: ' . $e->getMessage());
            return false;
        }
    }

    public function verifyPayment($invoiceId): array|bool
    {
        try {
            $sandboxMode = $this->getConfig('sandbox_mode', false);
            $apiKey = $sandboxMode ? $this->getConfig('api_key') : $this->getConfig('live_api_key');
            
            if (!$apiKey) {
                return false;
            }
            
            $apiBaseUrl = $sandboxMode ? 'https://sandbox.uddoktapay.com/' : $this->getConfig('live_base_url');
            
            // Verify payment with UddoktaPay API
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($apiBaseUrl . 'api/verify-payment', [
                'invoice_id' => $invoiceId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'COMPLETED') {
                    return [
                        'status' => 'success',
                        'amount' => $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? null
                    ];
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay verify error: ' . $e->getMessage());
            return false;
        }
    }

    private function getCustomerEmail($userId): string
    {
        $user = \App\Models\User::find($userId);
        return $user ? $user->email : '';
    }

    private function getCustomerName($userId): string
    {
        $user = \App\Models\User::find($userId);
        return $user ? $user->name : 'Customer';
    }

    private function getOrderAmount($tradeNo): ?float
    {
        $order = \App\Models\Order::where('trade_no', $tradeNo)->first();
        return $order ? $order->total_amount / 100 : null;
    }
} 