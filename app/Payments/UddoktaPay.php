<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use GuzzleHttp\Client;

class UddoktaPay implements PaymentInterface
{
    protected $config;
    protected $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new Client();
    }

    public function form(): array
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'description' => 'UddoktaPay API Key from Dashboard',
                'type' => 'input',
            ],
            'base_url' => [
                'label' => 'Base URL',
                'description' => 'UddoktaPay API Base URL (e.g., https://pay.your-domain.com)',
                'type' => 'input',
            ],
        ];
    }    public function pay($order): array
    {
        if (empty($this->config['api_key'])) {
            throw new ApiException('UddoktaPay API Key is required');
        }

        if (empty($this->config['base_url'])) {
            throw new ApiException('UddoktaPay Base URL is required');
        }

        try {
            $amount = sprintf('%.2f', $order['total_amount'] / 100);
            
            $params = [
                'full_name' => 'User_' . $order['user_id'],
                'email' => 'user_' . $order['user_id'] . '@example.com',
                'amount' => $amount,
                'metadata' => [
                    'trade_no' => $order['trade_no'],
                    'user_id' => $order['user_id']
                ],
                'redirect_url' => $order['return_url'],
                'cancel_url' => $order['return_url'],
                'webhook_url' => $order['notify_url'],
                'return_type' => 'GET'
            ];

            \Log::info('UddoktaPay payment request:', [
                'trade_no' => $order['trade_no'],
                'amount' => $amount,
                'params' => $params
            ]);

            $response = $this->client->request('POST', rtrim($this->config['base_url'], '/') . '/api/checkout-v2', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ],
                'json' => $params
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!$result) {
                \Log::error('UddoktaPay pay: Invalid response format');
                throw new ApiException('UddoktaPay payment creation failed - invalid response');
            }

            if (isset($result['status']) && $result['status'] === 'ERROR') {
                $errorMessage = $result['message'] ?? 'Unknown error';
                \Log::error('UddoktaPay pay: API returned error', $result);
                throw new ApiException('UddoktaPay payment creation failed: ' . $errorMessage);
            }

            if (!isset($result['payment_url'])) {
                \Log::error('UddoktaPay pay: Missing payment_url in response', $result);
                throw new ApiException('UddoktaPay payment creation failed - no payment URL');
            }

            \Log::info('UddoktaPay payment created successfully:', [
                'trade_no' => $order['trade_no'],
                'payment_url' => $result['payment_url']
            ]);

            return [
                'type' => 1, // Redirect to URL
                'data' => $result['payment_url']
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = 'HTTP error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $errorMessage .= ' Response: ' . $responseBody;
            }
            \Log::error('UddoktaPay payment HTTP error: ' . $errorMessage);
            throw new ApiException('UddoktaPay payment error: ' . $errorMessage);
        } catch (\Exception $e) {
            \Log::error('UddoktaPay payment error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApiException('UddoktaPay payment error: ' . $e->getMessage());
        }
    }public function notify($params)
    {
        try {
            // Validate API key from webhook header
            $headerApiKey = request()->header('RT-UDDOKTAPAY-API-KEY');
            if (empty($headerApiKey) || $headerApiKey !== $this->config['api_key']) {
                \Log::error('UddoktaPay webhook: Invalid API key');
                return false;
            }

            // Get webhook payload
            $payload = request()->getContent();
            $data = json_decode($payload, true);

            if (!$data) {
                \Log::error('UddoktaPay webhook: Invalid JSON data');
                return false;
            }

            // Log received webhook data for debugging
            \Log::info('UddoktaPay webhook received:', $data);

            // Check if payment is completed
            if (!isset($data['status']) || $data['status'] !== 'COMPLETED') {
                \Log::info('UddoktaPay webhook: Payment not completed, status: ' . ($data['status'] ?? 'unknown'));
                return false;
            }

            // Extract required fields
            $invoiceId = $data['invoice_id'] ?? null;
            if (!$invoiceId) {
                \Log::error('UddoktaPay webhook: Missing invoice_id');
                return false;
            }

            // Extract metadata
            if (!isset($data['metadata']) || !isset($data['metadata']['trade_no'])) {
                \Log::error('UddoktaPay webhook: Invalid metadata structure');
                return false;
            }

            $tradeNo = $data['metadata']['trade_no'];

            // Verify payment status by making API call
            $verificationResult = $this->verifyPayment($invoiceId);
            if (!$verificationResult || $verificationResult['status'] !== 'COMPLETED') {
                \Log::error('UddoktaPay webhook: Payment verification failed');
                return false;
            }

            \Log::info('UddoktaPay webhook: Payment verified successfully', [
                'trade_no' => $tradeNo,
                'invoice_id' => $invoiceId
            ]);

            return [
                'trade_no' => $tradeNo,
                'callback_no' => $invoiceId
            ];
        } catch (\Exception $e) {
            \Log::error('UddoktaPay webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }    protected function verifyPayment($invoiceId)
    {
        try {
            $response = $this->client->request('POST', rtrim($this->config['base_url'], '/') . '/api/verify-payment', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'RT-UDDOKTAPAY-API-KEY' => $this->config['api_key']
                ],
                'json' => [
                    'invoice_id' => $invoiceId
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!$result) {
                \Log::error('UddoktaPay verify: Invalid response format');
                return false;
            }

            if (isset($result['status']) && $result['status'] === 'ERROR') {
                \Log::error('UddoktaPay verify: API returned error', $result);
                return false;
            }

            if (!isset($result['status']) || $result['status'] !== 'COMPLETED') {
                \Log::error('UddoktaPay verify: Payment not completed', [
                    'status' => $result['status'] ?? 'unknown',
                    'invoice_id' => $invoiceId
                ]);
                return false;
            }

            \Log::info('UddoktaPay verify: Payment verified successfully', [
                'invoice_id' => $invoiceId,
                'status' => $result['status']
            ]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('UddoktaPay verify payment error: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
