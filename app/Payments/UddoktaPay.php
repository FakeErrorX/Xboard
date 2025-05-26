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

        // Prepare payment data
        $data = [
            'amount' => $amount,
            'full_name' => 'Customer', // You might want to replace with actual user name if available
            'email' => 'customer@example.com', // Replace with actual user email if available
            'metadata' => [
                'order_id' => $order['trade_no']
            ],
            'redirect_url' => $order['return_url'],
            'cancel_url' => $order['return_url'],
            'webhook_url' => $order['notify_url'],
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
        \Log::info('UddoktaPay callback received: ' . json_encode($params));
        
        // UddoktaPay sends the invoice_id as a query parameter to the redirect_url after payment
        // It doesn't send active notifications, so we need to check for the invoice_id from the redirect
        $invoice_id = null;
        
        // Check if the invoice_id is in the request query parameters
        if (isset($_GET['invoice_id'])) {
            $invoice_id = $_GET['invoice_id'];
            \Log::info('UddoktaPay invoice_id found in GET parameters: ' . $invoice_id);
        } 
        // Also check POST data as a fallback
        else if (isset($params['invoice_id'])) {
            $invoice_id = $params['invoice_id'];
            \Log::info('UddoktaPay invoice_id found in POST parameters: ' . $invoice_id);
        } else {
            \Log::error('UddoktaPay callback: Missing invoice_id in both GET and POST parameters');
            return false;
        }

        // Now we need to verify the payment status using the invoice_id
        return $this->verifyPayment($invoice_id);
    }

    /**
     * Verify payment status with UddoktaPay API
     * 
     * @param string $invoice_id
     * @return array|bool
     */
    protected function verifyPayment($invoice_id): array|bool
    {
        $baseUrl = rtrim($this->config['api_url'], '/');
        $apiKey = $this->config['api_key'];

        \Log::info('UddoktaPay verifying payment for invoice: ' . $invoice_id);

        // Prepare verification data
        $verifyData = [
            'invoice_id' => $invoice_id
        ];

        // Prepare cURL request to verify payment
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

        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            \Log::error('UddoktaPay verification error: ' . $err);
            return false;
        }

        $result = json_decode($response, true);
        \Log::info('UddoktaPay verification response: ' . json_encode($result));

        // Check if payment is completed
        if (isset($result['status'])) {
            $status = strtoupper($result['status']);
            
            if ($status === 'COMPLETED' && isset($result['metadata']['order_id'])) {
                \Log::info('UddoktaPay payment completed for order: ' . $result['metadata']['order_id']);
                return [
                    'trade_no' => $result['metadata']['order_id'],
                    'callback_no' => $result['transaction_id']
                ];
            } else {
                \Log::warning('UddoktaPay payment not completed. Status: ' . $status);
            }
        } else {
            \Log::error('UddoktaPay verification failed: Status field missing');
        }
        
        return false;
    }
} 