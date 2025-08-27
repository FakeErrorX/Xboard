<?php

namespace Plugin\PipraPay;

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
                $methods['PipraPay'] = [
                    'name' => $this->getConfig('display_name', 'PipraPay'),
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
            'mode' => [
                'label' => 'Payment Mode',
                'type' => 'select',
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox (Testing)'],
                    ['value' => 'live', 'label' => 'Live (Production)']
                ],
                'description' => 'Choose between sandbox for testing or live for production payments'
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Your PipraPay API Key from PipraPay dashboard'
            ],
            'base_url' => [
                'label' => 'Base URL',
                'type' => 'string',
                'required' => true,
                'description' => 'PipraPay API base URL (e.g., https://sandbox.piprapay.com for sandbox or https://api.piprapay.com for live)'
            ],
            'currency' => [
                'label' => 'Currency',
                'type' => 'select',
                'options' => [
                    ['value' => 'BDT', 'label' => 'BDT - Bangladeshi Taka'],
                    ['value' => 'USD', 'label' => 'USD - US Dollar']
                ],
                'description' => 'Payment currency for transactions'
            ],
            'display_name' => [
                'label' => 'Display Name',
                'type' => 'string',
                'description' => 'Custom display name for this payment method (optional)'
            ],
            'icon' => [
                'label' => 'Icon',
                'type' => 'string',
                'description' => 'Emoji or icon for this payment method (optional)'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $mode = $this->getConfig('mode', 'sandbox');
            $apiKey = $this->getConfig('api_key');
            $baseUrl = $this->getConfig('base_url');
            $currency = $this->getConfig('currency', 'BDT');
            
            if (!$apiKey) {
                throw new ApiException('API key is required. Please configure PipraPay API key in payment settings.');
            }
            
            if (!$baseUrl) {
                throw new ApiException('Base URL is required. Please configure PipraPay base URL in payment settings.');
            }
            
            // Get customer information
            $customerEmail = $this->getCustomerEmail($order['user_id']);
            $customerName = $this->getCustomerName($order['user_id']);
            
            // Initialize PipraPay SDK
            $pipraPay = new PipraPaySDK($apiKey, $baseUrl, $currency);
            
            // Calculate amount (PipraPay expects amount as number, not cents)
            $amount = $order['total_amount'] / 100;
            
            // Prepare payment data according to PipraPay API
            $paymentData = [
                'full_name' => $customerName,
                'email_mobile' => $customerEmail,
                'amount' => $amount,
                'metadata' => [
                    'trade_no' => $order['trade_no'],
                    'user_id' => (string)$order['user_id'],
                    'order_type' => 'xboard_subscription'
                ],
                'redirect_url' => $order['return_url'],
                'cancel_url' => $order['return_url'] . '?status=cancelled',
                'webhook_url' => $order['notify_url']
            ];

            Log::info('PipraPay: Initiating payment', [
                'trade_no' => $order['trade_no'],
                'amount' => $amount,
                'currency' => $currency,
                'mode' => $mode
            ]);

            // Create charge using PipraPay SDK
            $response = $pipraPay->createCharge($paymentData);

            Log::info('PipraPay: API Response', [
                'trade_no' => $order['trade_no'],
                'response' => $response
            ]);

            if (isset($response['status']) && $response['status'] && isset($response['pp_url'])) {
                return [
                    'type' => 1, // Redirect type
                    'data' => $response['pp_url']
                ];
            } else {
                $errorMessage = $response['error'] ?? 'Unknown error from PipraPay API';
                Log::error('PipraPay: Payment creation failed', [
                    'trade_no' => $order['trade_no'],
                    'error' => $errorMessage,
                    'response' => $response
                ]);
                throw new ApiException('Payment creation failed: ' . $errorMessage);
            }
            
        } catch (\Exception $e) {
            Log::error('PipraPay: Payment error', [
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
            $apiKey = $this->getConfig('api_key');
            
            if (!$apiKey) {
                Log::error('PipraPay: API key not configured for webhook validation');
                return false;
            }
            
            // Initialize PipraPay SDK for webhook handling
            $pipraPay = new PipraPaySDK($apiKey, '', '');
            
            // Handle webhook using PipraPay SDK
            $webhookResult = $pipraPay->handleWebhook($apiKey);
            
            if (!$webhookResult['status']) {
                Log::error('PipraPay: Unauthorized webhook', [
                    'message' => $webhookResult['message'] ?? 'Unknown error'
                ]);
                return false;
            }
            
            $webhookData = $webhookResult['data'];
            
            // Extract required data from webhook payload
            $ppId = $webhookData['pp_id'] ?? null;
            $status = $webhookData['status'] ?? null;
            $amount = $webhookData['amount'] ?? null;
            $metadata = $webhookData['metadata'] ?? [];
            $tradeNo = $metadata['trade_no'] ?? null;
            
            Log::info('PipraPay: Webhook received', [
                'pp_id' => $ppId,
                'status' => $status,
                'amount' => $amount,
                'trade_no' => $tradeNo
            ]);
            
            if (!$tradeNo || !$ppId) {
                Log::error('PipraPay: Missing required fields in webhook', [
                    'trade_no' => $tradeNo,
                    'pp_id' => $ppId
                ]);
                return false;
            }
            
            // Only process completed payments
            if ($status === 'COMPLETED' || $status === 'success') {
                // Additional verification: verify payment with PipraPay API
                $verificationResult = $this->verifyPayment($ppId);
                
                if (!$verificationResult) {
                    Log::error('PipraPay: Payment verification failed', [
                        'trade_no' => $tradeNo,
                        'pp_id' => $ppId
                    ]);
                    return false;
                }
                
                // Verify amount matches order
                if ($amount && !$this->verifyOrderAmount($tradeNo, $amount)) {
                    Log::error('PipraPay: Amount mismatch detected', [
                        'trade_no' => $tradeNo,
                        'webhook_amount' => $amount
                    ]);
                    return false;
                }
                
                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $ppId
                ];
            } else {
                Log::info('PipraPay: Payment not completed, status: ' . $status, [
                    'trade_no' => $tradeNo,
                    'pp_id' => $ppId
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('PipraPay: Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verify payment using PipraPay API
     */
    public function verifyPayment($ppId): array|bool
    {
        try {
            $apiKey = $this->getConfig('api_key');
            $baseUrl = $this->getConfig('base_url');
            $currency = $this->getConfig('currency', 'BDT');
            
            if (!$apiKey || !$baseUrl) {
                Log::error('PipraPay: API configuration not complete for verification');
                return false;
            }
            
            // Initialize PipraPay SDK
            $pipraPay = new PipraPaySDK($apiKey, $baseUrl, $currency);
            
            Log::info('PipraPay: Verifying payment', [
                'pp_id' => $ppId
            ]);
            
            // Verify payment with PipraPay API
            $response = $pipraPay->verifyPayment($ppId);
            
            if (isset($response['status']) && $response['status']) {
                Log::info('PipraPay: Verification successful', [
                    'pp_id' => $ppId,
                    'response' => $response
                ]);
                return $response;
            } else {
                Log::error('PipraPay: Verification failed', [
                    'pp_id' => $ppId,
                    'error' => $response['error'] ?? 'Unknown error'
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('PipraPay: Verification error', [
                'pp_id' => $ppId,
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
            Log::warning('PipraPay: Failed to get customer email', ['user_id' => $userId]);
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
            Log::warning('PipraPay: Failed to get customer name', ['user_id' => $userId]);
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
                Log::error('PipraPay: Order not found for verification', ['trade_no' => $tradeNo]);
                return false;
            }
            
            $expectedAmount = $order->total_amount / 100; // Convert from cents
            $receivedAmount = (float)$webhookAmount;
            
            // Allow small floating point differences
            $difference = abs($expectedAmount - $receivedAmount);
            if ($difference > 0.01) {
                Log::error('PipraPay: Amount mismatch', [
                    'trade_no' => $tradeNo,
                    'expected' => $expectedAmount,
                    'received' => $receivedAmount,
                    'difference' => $difference
                ]);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('PipraPay: Error verifying order amount', [
                'trade_no' => $tradeNo,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

/**
 * PipraPay SDK Class
 * Based on the official PipraPay Laravel SDK
 */
class PipraPaySDK
{
    protected $api_key;
    protected $base_url;
    protected $currency;

    public function __construct($api_key, $base_url, $currency = 'BDT')
    {
        $this->api_key = $api_key;
        $this->base_url = rtrim($base_url, '/');
        $this->currency = $currency;
    }

    public function createCharge($data)
    {
        $data['currency'] = $this->currency;
        return $this->post('/api/create-charge', $data);
    }

    public function verifyPayment($pp_id)
    {
        return $this->post('/api/verify-payments', ['pp_id' => $pp_id]);
    }

    public function handleWebhook($expected_api_key)
    {
        $received_key = request()->header('mh-piprapay-api-key');

        if ($received_key !== $expected_api_key) {
            return ['status' => false, 'message' => 'Unauthorized'];
        }

        return ['status' => true, 'data' => request()->all()];
    }

    protected function post($endpoint, $data)
    {
        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'mh-piprapay-api-key' => $this->api_key
            ])->timeout(30)->post($this->base_url . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => 'Network error: ' . $e->getMessage()];
        }
    }
}
