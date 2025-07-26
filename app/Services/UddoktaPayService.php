<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\User;

class UddoktaPayService
{
    protected $apiKey;
    protected $apiBaseUrl;
    protected $sandboxMode;
    protected $currency;

    public function __construct()
    {
        $this->apiKey = config('uddoktapay.api_key');
        $this->apiBaseUrl = config('uddoktapay.api_base_url');
        $this->sandboxMode = config('uddoktapay.sandbox_mode', true);
        $this->currency = config('uddoktapay.currency', 'BDT');
    }

    /**
     * Create a new payment using UddoktaPay Create Charge API
     */
    public function createPayment(array $orderData): array
    {
        try {
            // Get customer information
            $customerEmail = $this->getCustomerEmail($orderData['user_id']);
            $customerName = $this->getCustomerName($orderData['user_id']);
            
            $paymentData = [
                'full_name' => $customerName,
                'email' => $customerEmail,
                'amount' => (string)($orderData['total_amount'] / 100), // Convert from cents to string
                'metadata' => [
                    'trade_no' => $orderData['trade_no'],
                    'user_id' => (string)$orderData['user_id'],
                    'order_type' => 'xboard_subscription'
                ],
                'redirect_url' => $orderData['return_url'],
                'cancel_url' => $orderData['return_url'] . '?status=cancelled',
                'webhook_url' => $orderData['notify_url'],
                'return_type' => 'GET'
            ];

            // Determine API base URL based on sandbox mode
            // Using official UddoktaPay sandbox URL from API Information
            $apiBaseUrl = $this->sandboxMode ? 'https://sandbox.uddoktapay.com/' : 'https://pay.uddoktapay.com/';

            $response = Http::timeout(30)
                ->withHeaders([
                    'RT-UDDOKTAPAY-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($apiBaseUrl . 'api/checkout-v2', $paymentData);

            if (!$response->successful()) {
                Log::error('UddoktaPay payment creation failed', [
                    'response' => $response->json(),
                    'order_data' => $orderData,
                    'status_code' => $response->status(),
                    'sandbox_mode' => $this->sandboxMode,
                    'api_base_url' => $apiBaseUrl
                ]);
                throw new \Exception('Failed to create payment: ' . $response->body());
            }

            $paymentResponse = $response->json();
            
            if (!$paymentResponse['status']) {
                Log::error('UddoktaPay payment creation failed', [
                    'message' => $paymentResponse['message'] ?? 'Unknown error',
                    'order_data' => $orderData
                ]);
                throw new \Exception('Payment creation failed: ' . ($paymentResponse['message'] ?? 'Unknown error'));
            }
            
            Log::info('UddoktaPay payment created successfully', [
                'payment_url' => $paymentResponse['payment_url'] ?? null,
                'trade_no' => $orderData['trade_no'],
                'amount' => $paymentData['amount'],
                'sandbox_mode' => $this->sandboxMode,
                'api_base_url' => $apiBaseUrl
            ]);

            return [
                'payment_url' => $paymentResponse['payment_url'],
                'status' => 'pending'
            ];

        } catch (\Exception $e) {
            Log::error('UddoktaPay payment creation error', [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
                'sandbox_mode' => $this->sandboxMode
            ]);
            throw $e;
        }
    }

    /**
     * Verify payment via UddoktaPay Verify Payment API
     */
    public function verifyPayment(string $invoiceId): array|bool
    {
        try {
            // Determine API base URL based on sandbox mode
            // Using official UddoktaPay sandbox URL from API Information
            $apiBaseUrl = $this->sandboxMode ? 'https://sandbox.uddoktapay.com/' : 'https://pay.uddoktapay.com/';
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'RT-UDDOKTAPAY-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($apiBaseUrl . 'api/verify-payment', [
                    'invoice_id' => $invoiceId
                ]);

            if (!$response->successful()) {
                Log::error('UddoktaPay payment verification failed', [
                    'invoice_id' => $invoiceId,
                    'response' => $response->json(),
                    'status_code' => $response->status(),
                    'sandbox_mode' => $this->sandboxMode,
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
                    'sandbox_mode' => $this->sandboxMode
                ]);
                return false;
            }

            Log::info('UddoktaPay payment verification successful', [
                'invoice_id' => $invoiceId,
                'status' => $paymentData['status'] ?? 'unknown',
                'amount' => $paymentData['amount'] ?? 0,
                'payment_method' => $paymentData['payment_method'] ?? 'unknown',
                'sandbox_mode' => $this->sandboxMode,
                'api_base_url' => $apiBaseUrl
            ]);

            return $paymentData;

        } catch (\Exception $e) {
            Log::error('UddoktaPay payment verification error', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
                'sandbox_mode' => $this->sandboxMode
            ]);
            return false;
        }
    }

    /**
     * Process payment notification (called when user returns from payment)
     */
    public function processPaymentNotification(string $invoiceId): array|bool
    {
        try {
            // Verify the payment using the API
            $paymentData = $this->verifyPayment($invoiceId);
            
            if (!$paymentData) {
                Log::error('UddoktaPay payment verification failed for notification', [
                    'invoice_id' => $invoiceId
                ]);
                return false;
            }

            // Check if payment is completed
            if ($paymentData['status'] !== 'COMPLETED') {
                Log::info('UddoktaPay payment not completed', [
                    'invoice_id' => $invoiceId,
                    'status' => $paymentData['status']
                ]);
                return false;
            }

            // Extract trade number from metadata
            $tradeNo = $paymentData['metadata']['trade_no'] ?? '';
            
            if (!$tradeNo) {
                Log::error('UddoktaPay payment missing trade_no in metadata', [
                    'invoice_id' => $invoiceId,
                    'payment_data' => $paymentData
                ]);
                return false;
            }

            Log::info('UddoktaPay payment notification processed successfully - order will be completed', [
                'invoice_id' => $invoiceId,
                'trade_no' => $tradeNo,
                'amount' => $paymentData['amount'] ?? 0,
                'payment_method' => $paymentData['payment_method'] ?? 'unknown'
            ]);

            return [
                'trade_no' => $tradeNo,
                'callback_no' => $invoiceId,
                'amount' => $paymentData['amount'] ?? 0,
                'currency' => $this->currency,
                'payment_method' => $paymentData['payment_method'] ?? 'unknown',
                'transaction_id' => $paymentData['transaction_id'] ?? '',
                'sender_number' => $paymentData['sender_number'] ?? '',
                'completion_required' => true // Flag to indicate order should be completed
            ];

        } catch (\Exception $e) {
            Log::error('UddoktaPay payment notification processing error', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId
            ]);
            return false;
        }
    }

    /**
     * Get customer email from user ID
     */
    private function getCustomerEmail(int $userId): string
    {
        $user = User::find($userId);
        return $user ? $user->email : 'customer@example.com';
    }

    /**
     * Get customer name from user ID
     */
    private function getCustomerName(int $userId): string
    {
        $user = User::find($userId);
        return $user ? ($user->name ?? $user->username ?? 'Customer') : 'Customer';
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
} 