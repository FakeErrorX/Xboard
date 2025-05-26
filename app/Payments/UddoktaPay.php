<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;

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
            'api_url' => [
                'label' => 'API URL',
                'description' => 'UddoktaPay API URL (e.g., https://pay.your-domain.com)',
                'type' => 'input',
            ],
            'api_key' => [
                'label' => 'API Key',
                'description' => 'Your UddoktaPay API Key',
                'type' => 'input',
            ],
            'rate' => [
                'label' => 'Exchange Rate',
                'description' => 'UddoktaPay uses BDT (Bangladeshi Taka) as its base currency. If your site uses a different currency, please specify the exchange rate.',
                'type' => 'input',
                'default' => '1'
            ],
        ];
    }

    public function pay($order): array
    {
        $baseUrl = rtrim($this->config['api_url'], '/');
        $apiKey = $this->config['api_key'];
        
        // Apply currency conversion if rate is set
        $amount = $order['total_amount'] / 100; // Convert from cents to actual currency unit
        if (isset($this->config['rate']) && $this->config['rate'] > 0) {
            $amount = $amount * $this->config['rate'];
        }

        // Ensure URLs are HTTPS and absolute
        $returnUrl = $this->ensureHttpsUrl($order['return_url']);
        $notifyUrl = $this->ensureHttpsUrl($order['notify_url']);

        // Prepare payment data
        $data = [
            'amount' => $amount,
            'full_name' => 'Customer', // You might want to replace with actual user name if available
            'email' => 'customer@example.com', // Replace with actual user email if available
            'metadata' => [
                'order_id' => $order['trade_no']
            ],
            'redirect_url' => $returnUrl,
            'cancel_url' => $returnUrl,
            'webhook_url' => $notifyUrl,
        ];

        // Prepare cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/checkout-v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'RT-UDDOKTAPAY-API-KEY: ' . $apiKey,
            'accept: application/json',
            'content-type: application/json'
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            \Log::error('UddoktaPay payment error: ' . $err);
            throw new ApiException('Payment gateway error: ' . $err);
        }

        $result = json_decode($response, true);

        if (isset($result['status']) && $result['status'] === true && isset($result['payment_url'])) {
            // Store the invoice_id in session for verification
            if (isset($result['invoice_id'])) {
                session(['uddoktapay_invoice_id' => $result['invoice_id']]);
                // Also store in cache for webhook verification
                \Cache::put('uddoktapay_invoice_' . $result['invoice_id'], $order['trade_no'], now()->addHours(24));
            }
            
            return [
                'type' => 1, // Redirect to url
                'data' => $result['payment_url']
            ];
        } else {
            $message = isset($result['message']) ? $result['message'] : 'Unknown error';
            \Log::error('UddoktaPay payment error: ' . $message);
            throw new ApiException('Payment error: ' . $message);
        }
    }

    public function notify($params): array|bool
    {
        \Log::info('UddoktaPay webhook received: ' . json_encode($params));
        
        // Get invoice_id from multiple sources
        $invoice_id = $_GET['invoice_id'] ?? $params['invoice_id'] ?? session('uddoktapay_invoice_id') ?? null;
        
        if (!$invoice_id) {
            \Log::error('UddoktaPay webhook: Missing invoice_id');
            return false;
        }

        // Try to get order_id from cache first (faster)
        $order_id = \Cache::get('uddoktapay_invoice_' . $invoice_id);
        
        \Log::info('UddoktaPay verifying payment for invoice: ' . $invoice_id);
        
        // Verify payment status
        $baseUrl = rtrim($this->config['api_url'], '/');
        $apiKey = $this->config['api_key'];

        $verifyData = [
            'invoice_id' => $invoice_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/verify-payment');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verifyData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'RT-UDDOKTAPAY-API-KEY: ' . $apiKey,
            'accept: application/json',
            'content-type: application/json'
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            \Log::error('UddoktaPay verification error: ' . $err);
            return false;
        }

        $result = json_decode($response, true);
        \Log::info('UddoktaPay verification response: ' . json_encode($result));

        // Check payment status
        if (isset($result['status'])) {
            $status = strtoupper($result['status']);
            
            if ($status === 'COMPLETED') {
                // Use order_id from cache if available, otherwise from metadata
                $order_id = $order_id ?? $result['metadata']['order_id'] ?? null;
                
                if ($order_id) {
                    \Log::info('UddoktaPay payment completed for order: ' . $order_id);
                    // Clear the stored data
                    session()->forget('uddoktapay_invoice_id');
                    \Cache::forget('uddoktapay_invoice_' . $invoice_id);
                    
                    return [
                        'trade_no' => $order_id,
                        'callback_no' => $result['transaction_id'] ?? $invoice_id,
                        'custom_result' => '{"returnCode": "success","returnMsg": ""}'
                    ];
                }
            } else {
                \Log::warning('UddoktaPay payment not completed. Status: ' . $status);
            }
        } else {
            \Log::error('UddoktaPay verification failed: Status field missing');
        }
        
        return false;
    }

    /**
     * Ensure URL is HTTPS and absolute
     * 
     * @param string $url
     * @return string
     */
    protected function ensureHttpsUrl($url): string
    {
        // If URL is relative, make it absolute using app URL
        if (strpos($url, 'http') !== 0) {
            $url = config('app.url') . '/' . ltrim($url, '/');
        }
        
        // Force HTTPS if not already
        if (strpos($url, 'https://') !== 0) {
            $url = 'https://' . substr($url, strpos($url, '://') + 3);
        }
        
        return $url;
    }
} 