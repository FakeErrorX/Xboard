<?php

namespace Plugin\Uddoktapay;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin implements PaymentInterface
{
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
            'display_name' => [
                'label' => 'Display Name',
                'type' => 'string',
                'description' => 'Payment method name shown to users'
            ],
            'icon' => [
                'label' => 'Icon',
                'type' => 'string',
                'description' => 'Icon displayed next to payment method'
            ],
            'mode' => [
                'label' => 'Payment Mode',
                'type' => 'select',
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox (Testing)'],
                    ['value' => 'live', 'label' => 'Live (Production)']
                ],
                'description' => 'Choose between sandbox for testing or live for production payments'
            ],
            'live_api_key' => [
                'label' => 'Live API Key',
                'type' => 'string',
                'description' => 'Your UddoktaPay Live API Key (required for live mode only)'
            ],
            'live_base_url' => [
                'label' => 'Live Base URL',
                'type' => 'string',
                'description' => 'Your UddoktaPay Live installation URL (required for live mode only)'
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
                'description' => 'Payment currency (BDT for Bangladeshi methods, others for global)'
            ],
            'payment_type' => [
                'label' => 'Payment Type',
                'type' => 'select',
                'options' => [
                    ['value' => 'bangladeshi', 'label' => 'Bangladeshi Methods (bKash, Rocket, Nagad, etc.)'],
                    ['value' => 'global', 'label' => 'Global Methods (Cards, PayPal, etc.)']
                ],
                'description' => 'Choose payment method type based on your target audience'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $mode = $this->getConfig('mode', 'sandbox');
            $sandboxMode = ($mode === 'sandbox');
            $apiKey = $sandboxMode ? '982d381360a69d419689740d9f2e26ce36fb7a50' : $this->getConfig('live_api_key');
            $currency = $this->getConfig('currency', 'BDT');
            $paymentType = $this->getConfig('payment_type', 'bangladeshi');
            
            if (!$apiKey) {
                throw new ApiException('API key is required. Please configure UddoktaPay API key in payment settings.');
            }
            
            // Get customer information
            $customerEmail = $this->getCustomerEmail($order['user_id']);
            $customerName = $this->getCustomerName($order['user_id']);
            
            // Determine API base URL and endpoint based on sandbox mode
            if ($sandboxMode) {
                $apiBaseUrl = 'https://sandbox.uddoktapay.com/';
            } else {
                $apiBaseUrl = $this->getConfig('live_base_url');
                if (!$apiBaseUrl) {
                    throw new ApiException('Live base URL is required for production mode');
                }
                if (!str_ends_with($apiBaseUrl, '/')) {
                    $apiBaseUrl .= '/';
                }
            }
            
            // Determine the correct API endpoint based on payment type
            $endpoint = ($paymentType === 'global') ? 'api/checkout-global' : 'api/checkout-v2';
            
            // Calculate amount (UddoktaPay expects string format)
            $amount = number_format($order['total_amount'] / 100, 2, '.', '');
            
            // Prepare payment data according to UddoktaPay API
            $paymentData = [
                'full_name' => $customerName,
                'email' => $customerEmail,
                'amount' => $amount,
                'metadata' => [
                    'trade_no' => $order['trade_no'],
                    'user_id' => (string)$order['user_id'],
                    'order_type' => 'xboard_subscription',
                    'currency' => $currency
                ],
                'redirect_url' => url('/api/v1/guest/uddoktapay/return'),
                'cancel_url' => $order['return_url'] . '?status=cancelled',
                'webhook_url' => $order['notify_url'],
                'return_type' => 'GET'
            ];

            Log::info('UddoktaPay: Initiating payment', [
                'trade_no' => $order['trade_no'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_type' => $paymentType,
                'endpoint' => $endpoint,
                'sandbox' => $sandboxMode
            ]);

            // Make API request to UddoktaPay
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->timeout(30)->post($apiBaseUrl . $endpoint, $paymentData);

            $responseData = $response->json();

            Log::info('UddoktaPay: API Response', [
                'status_code' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful() && isset($responseData['payment_url'])) {
                return [
                    'type' => 1, // Redirect type
                    'data' => $responseData['payment_url']
                ];
            } else {
                $errorMessage = $responseData['message'] ?? 'Unknown error from UddoktaPay API';
                Log::error('UddoktaPay: Payment creation failed', [
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $responseData
                ]);
                throw new ApiException('Payment creation failed: ' . $errorMessage);
            }
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay: Payment error', [
                'trade_no' => $order['trade_no'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApiException('Payment initialization failed: ' . $e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        try {
            $mode = $this->getConfig('mode', 'sandbox');
            $sandboxMode = ($mode === 'sandbox');
            $apiKey = $sandboxMode ? '982d381360a69d419689740d9f2e26ce36fb7a50' : $this->getConfig('live_api_key');
            
            if (!$apiKey) {
                Log::error('UddoktaPay: API key not configured for webhook validation');
                return false;
            }
            
            // Verify the webhook API key (following UddoktaPay documentation)
            $headerApiKey = $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ?? null;
            
            if (!$headerApiKey || $headerApiKey !== $apiKey) {
                Log::error('UddoktaPay: Unauthorized webhook - API key mismatch', [
                    'expected_key_prefix' => substr($apiKey, 0, 8) . '...',
                    'received_key_prefix' => $headerApiKey ? substr($headerApiKey, 0, 8) . '...' : 'null'
                ]);
                return false;
            }
            
            // Extract required data from webhook payload
            $invoiceId = $params['invoice_id'] ?? null;
            $status = $params['status'] ?? null;
            $amount = $params['amount'] ?? null;
            $metadata = $params['metadata'] ?? [];
            $tradeNo = $metadata['trade_no'] ?? null;
            
            Log::info('UddoktaPay: Webhook received', [
                'invoice_id' => $invoiceId,
                'status' => $status,
                'amount' => $amount,
                'trade_no' => $tradeNo,
                'payment_method' => $params['payment_method'] ?? 'unknown'
            ]);
            
            if (!$tradeNo || !$invoiceId) {
                Log::error('UddoktaPay: Missing required fields in webhook', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId
                ]);
                return false;
            }
            
            // Only process completed payments
            if ($status === 'COMPLETED') {
                // Additional verification: check amount matches order
                if ($amount && !$this->verifyOrderAmount($tradeNo, $amount)) {
                    Log::error('UddoktaPay: Amount mismatch detected', [
                        'trade_no' => $tradeNo,
                        'webhook_amount' => $amount
                    ]);
                    return false;
                }
                
                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $invoiceId
                ];
            } else {
                Log::info('UddoktaPay: Payment not completed, status: ' . $status, [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay: Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verify payment using UddoktaPay API
     */
    public function verifyPayment($invoiceId): array|bool
    {
        try {
            $mode = $this->getConfig('mode', 'sandbox');
            $sandboxMode = ($mode === 'sandbox');
            $apiKey = $sandboxMode ? '982d381360a69d419689740d9f2e26ce36fb7a50' : $this->getConfig('live_api_key');
            
            if (!$apiKey) {
                Log::error('UddoktaPay: API key not configured for verification');
                return false;
            }
            
            // Determine API base URL
            if ($sandboxMode) {
                $apiBaseUrl = 'https://sandbox.uddoktapay.com/';
            } else {
                $apiBaseUrl = $this->getConfig('live_base_url');
                if (!$apiBaseUrl || !str_ends_with($apiBaseUrl, '/')) {
                    $apiBaseUrl .= '/';
                }
            }
            
            Log::info('UddoktaPay: Verifying payment', [
                'invoice_id' => $invoiceId,
                'sandbox' => $sandboxMode
            ]);
            
            // Verify payment with UddoktaPay API
            $response = Http::withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->timeout(30)->post($apiBaseUrl . 'api/verify-payment', [
                'invoice_id' => $invoiceId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('UddoktaPay: Verification response', [
                    'invoice_id' => $invoiceId,
                    'status' => $data['status'] ?? 'unknown',
                    'amount' => $data['amount'] ?? 'unknown'
                ]);
                
                if (isset($data['status']) && $data['status'] === 'COMPLETED') {
                    return [
                        'status' => 'COMPLETED',
                        'amount' => $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? null,
                        'payment_method' => $data['payment_method'] ?? null,
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'metadata' => $data['metadata'] ?? []
                    ];
                } else {
                    Log::warning('UddoktaPay: Payment not completed', [
                        'invoice_id' => $invoiceId,
                        'status' => $data['status'] ?? 'unknown'
                    ]);
                    return false;
                }
            } else {
                $errorData = $response->json();
                Log::error('UddoktaPay: Verification failed', [
                    'invoice_id' => $invoiceId,
                    'status_code' => $response->status(),
                    'error' => $errorData['message'] ?? 'Unknown error'
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('UddoktaPay: Verification error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get customer email from user ID
     */
    private function getCustomerEmail($userId): string
    {
        try {
            $user = \App\Models\User::find($userId);
            return $user ? $user->email : 'noreply@xboard.local';
        } catch (\Exception $e) {
            Log::warning('UddoktaPay: Failed to get customer email', ['user_id' => $userId]);
            return 'noreply@xboard.local';
        }
    }

    /**
     * Get customer name from user ID
     */
    private function getCustomerName($userId): string
    {
        try {
            $user = \App\Models\User::find($userId);
            if ($user) {
                // Try to get name from email if no name field exists
                $name = $user->name ?? $user->full_name ?? explode('@', $user->email)[0];
                return $name ?: 'Xboard Customer';
            }
            return 'Xboard Customer';
        } catch (\Exception $e) {
            Log::warning('UddoktaPay: Failed to get customer name', ['user_id' => $userId]);
            return 'Xboard Customer';
        }
    }

    /**
     * Verify order amount matches webhook amount
     */
    private function verifyOrderAmount($tradeNo, $webhookAmount): bool
    {
        try {
            $order = \App\Models\Order::where('trade_no', $tradeNo)->first();
            if (!$order) {
                Log::error('UddoktaPay: Order not found for verification', ['trade_no' => $tradeNo]);
                return false;
            }
            
            $expectedAmount = number_format($order->total_amount / 100, 2, '.', '');
            $receivedAmount = number_format((float)$webhookAmount, 2, '.', '');
            
            if ($expectedAmount !== $receivedAmount) {
                Log::error('UddoktaPay: Amount mismatch', [
                    'trade_no' => $tradeNo,
                    'expected' => $expectedAmount,
                    'received' => $receivedAmount
                ]);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('UddoktaPay: Error verifying order amount', [
                'trade_no' => $tradeNo,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 