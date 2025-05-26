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
        // Store the order in cache for verification on return
        // This approach doesn't rely on UddoktaPay's redirect behavior
        \Cache::put('uddoktapay_order_' . $order['trade_no'], $order, now()->addHours(2));
        
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
            // Use the return URL directly since we'll handle verification via cache
            'redirect_url' => $order['return_url'],
            'cancel_url' => $order['return_url'],
            'webhook_url' => $order['notify_url'],
            'return_type' => 'GET' // Explicitly set return type to GET
        ];

        try {
            // Log the request for debugging
            Log::info('UddoktaPay payment request', [
                'url' => "{$baseUrl}/api/checkout-v2",
                'payload' => $payload,
                'trade_no' => $order['trade_no']
            ]);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
            ])->post("{$baseUrl}/api/checkout-v2", $payload);

            // Log the response for debugging
            Log::info('UddoktaPay payment response', [
                'status' => $response->status(),
                'body' => $response->json(),
                'trade_no' => $order['trade_no']
            ]);
            
            $responseData = $response->json();
            
            if (!$response->successful() || !isset($responseData['payment_url'])) {
                $errorMessage = $responseData['message'] ?? 'Payment initialization failed';
                throw new ApiException($errorMessage);
            }
            
            // Store the invoice_id if available
            if (isset($responseData['invoice_id'])) {
                \Cache::put('uddoktapay_invoice_' . $order['trade_no'], $responseData['invoice_id'], now()->addHours(2));
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
        // Log all request information in detail
        $requestInfo = [
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'params' => $params,
            'query' => request()->query(),
            'post' => request()->post(),
            'content' => request()->getContent(),
            'headers' => request()->header()
        ];
        
        Log::info('UddoktaPay notification received', $requestInfo);
        
        // Check if this is a return from payment page with invoice_id
        $invoiceId = request()->query('invoice_id');
        if ($invoiceId) {
            try {
                // Verify the payment
                $baseUrl = rtrim($this->config['base_url'], '/');
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ])->post("{$baseUrl}/api/verify-payment", [
                    'invoice_id' => $invoiceId
                ]);
                
                Log::info('UddoktaPay verification response (redirect)', [
                    'invoice_id' => $invoiceId,
                    'response' => $response->json()
                ]);
                
                $paymentData = $response->json();
                
                // Check if payment was successful
                if ($response->successful() && 
                    isset($paymentData['metadata']['order_id']) && 
                    $paymentData['status'] === 'COMPLETED') {
                    
                    $tradeNo = $paymentData['metadata']['order_id'];
                    $transactionId = $paymentData['transaction_id'] ?? $invoiceId;
                    
                    // Log successful verification
                    Log::info('UddoktaPay payment verified successfully', [
                        'trade_no' => $tradeNo,
                        'transaction_id' => $transactionId
                    ]);
                    
                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $transactionId,
                        'custom_result' => json_encode(['status' => 'success'])
                    ];
                }
            } catch (\Exception $e) {
                Log::error('UddoktaPay return verification error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Try to extract invoice_id from different possible sources
        $extractedInvoiceId = null;
        
        // Try to get from URL parameters
        if (request()->query('invoice_id')) {
            $extractedInvoiceId = request()->query('invoice_id');
        }
        
        // Try to get from POST data 
        if (!$extractedInvoiceId && request()->post('invoice_id')) {
            $extractedInvoiceId = request()->post('invoice_id');
        }
        
        // Try to get from JSON body
        if (!$extractedInvoiceId && !empty($params) && isset($params['invoice_id'])) {
            $extractedInvoiceId = $params['invoice_id'];
        } 
        
        // Try to parse JSON content
        if (!$extractedInvoiceId && request()->getContent()) {
            try {
                $jsonData = json_decode(request()->getContent(), true);
                if (is_array($jsonData) && isset($jsonData['invoice_id'])) {
                    $extractedInvoiceId = $jsonData['invoice_id'];
                }
            } catch (\Exception $e) {
                // Ignore JSON parsing errors
            }
        }
        
        // If we found an invoice_id, verify the payment
        if ($extractedInvoiceId) {
            try {
                // Verify payment status with UddoktaPay API
                $baseUrl = rtrim($this->config['base_url'], '/');
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ])->post("{$baseUrl}/api/verify-payment", [
                    'invoice_id' => $extractedInvoiceId
                ]);
                
                Log::info('UddoktaPay webhook verification response', [
                    'invoice_id' => $extractedInvoiceId,
                    'response' => $response->json()
                ]);
                
                $paymentData = $response->json();
                
                // Check if payment was successful
                if ($response->successful() && 
                    isset($paymentData['metadata']['order_id']) && 
                    $paymentData['status'] === 'COMPLETED') {
                    
                    $tradeNo = $paymentData['metadata']['order_id'];
                    $transactionId = $paymentData['transaction_id'] ?? $extractedInvoiceId;
                    
                    // Log successful verification
                    Log::info('UddoktaPay webhook payment verified', [
                        'trade_no' => $tradeNo,
                        'transaction_id' => $transactionId
                    ]);
                    
                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $transactionId,
                        'custom_result' => json_encode(['status' => 'success'])
                    ];
                }
            } catch (\Exception $e) {
                Log::error('UddoktaPay webhook verification error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // If we couldn't verify the payment, return false
        Log::warning('UddoktaPay notification could not be verified');
        return false;
    }
}
