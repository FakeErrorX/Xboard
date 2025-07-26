<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\UddoktaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Plugin\HookManager;

class UddoktaPayController extends Controller
{
    /**
     * Handle UddoktaPay webhook notifications
     * This follows the official UddoktaPay webhook validation documentation
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('UddoktaPay webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->getContent()
            ]);

            // Get the API key from the request headers (as per UddoktaPay documentation)
            $headerApiKey = $request->header('RT-UDDOKTAPAY-API-KEY');
            $configApiKey = config('uddoktapay.api_key');

            // Verify the API key (as per UddoktaPay documentation)
            if (!$headerApiKey || $headerApiKey !== $configApiKey) {
                Log::error('UddoktaPay webhook unauthorized - invalid API key', [
                    'header_api_key' => $headerApiKey,
                    'config_api_key' => $configApiKey ? '***' : 'not_set'
                ]);
                return response()->json(['error' => 'Unauthorized Action'], 401);
            }

            // Get the request body (raw data)
            $rawData = $request->getContent();

            // Parse the JSON data
            $webhookData = json_decode($rawData, true);

            // Check if JSON data was successfully parsed
            if ($webhookData === null) {
                Log::error('UddoktaPay webhook invalid JSON data', ['raw_data' => $rawData]);
                return response()->json(['error' => 'Invalid JSON data'], 400);
            }

            Log::info('UddoktaPay webhook data parsed successfully', [
                'webhook_data' => $webhookData
            ]);

            // Extract data from webhook payload (as per UddoktaPay documentation)
            $invoiceId = $webhookData['invoice_id'] ?? '';
            $status = $webhookData['status'] ?? '';
            $metadata = $webhookData['metadata'] ?? [];
            $tradeNo = $metadata['trade_no'] ?? '';

            if (!$invoiceId) {
                Log::error('UddoktaPay webhook missing invoice_id', ['webhook_data' => $webhookData]);
                return response()->json(['error' => 'Missing invoice_id'], 400);
            }

            if (!$tradeNo) {
                Log::error('UddoktaPay webhook missing trade_no in metadata', ['webhook_data' => $webhookData]);
                return response()->json(['error' => 'Missing trade_no in metadata'], 400);
            }

            // Check if payment is completed
            if ($status !== 'COMPLETED') {
                Log::info('UddoktaPay webhook payment not completed', [
                    'invoice_id' => $invoiceId,
                    'status' => $status
                ]);
                return response()->json(['status' => 'ignored - payment not completed'], 200);
            }

            Log::info('UddoktaPay webhook payment completed', [
                'invoice_id' => $invoiceId,
                'trade_no' => $tradeNo,
                'status' => $status,
                'amount' => $webhookData['amount'] ?? 0,
                'payment_method' => $webhookData['payment_method'] ?? 'unknown'
            ]);

            // Process the payment in Xboard
            if (!$this->processPayment($tradeNo, $invoiceId, $webhookData)) {
                return response()->json(['error' => 'Order processing failed'], 500);
            }

            Log::info('UddoktaPay webhook payment processed successfully', [
                'invoice_id' => $invoiceId,
                'trade_no' => $tradeNo
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('UddoktaPay webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process the payment in Xboard system
     */
    private function processPayment(string $tradeNo, string $invoiceId, array $paymentData): bool
    {
        try {
            $order = Order::where('trade_no', $tradeNo)->first();
            
            if (!$order) {
                Log::error('UddoktaPay order not found', ['trade_no' => $tradeNo]);
                return false;
            }

            if ($order->status !== Order::STATUS_PENDING) {
                Log::info('UddoktaPay order already processed', [
                    'trade_no' => $tradeNo,
                    'status' => $order->status
                ]);
                return true;
            }

            // First mark as paid (processing)
            $orderService = new OrderService($order);
            
            if (!$orderService->paid($invoiceId)) {
                Log::error('UddoktaPay order payment processing failed', ['trade_no' => $tradeNo]);
                return false;
            }

            // Then mark as completed
            if (!$this->completeOrder($order)) {
                Log::error('UddoktaPay order completion failed', ['trade_no' => $tradeNo]);
                return false;
            }

            // Log additional payment details
            Log::info('UddoktaPay order payment completed', [
                'trade_no' => $tradeNo,
                'invoice_id' => $invoiceId,
                'amount' => $paymentData['amount'] ?? 0,
                'payment_method' => $paymentData['payment_method'] ?? 'unknown',
                'transaction_id' => $paymentData['transaction_id'] ?? '',
                'sender_number' => $paymentData['sender_number'] ?? ''
            ]);

            HookManager::call('payment.notify.success', $order);
            
            return true;

        } catch (\Exception $e) {
            Log::error('UddoktaPay payment processing error', [
                'error' => $e->getMessage(),
                'trade_no' => $tradeNo
            ]);
            return false;
        }
    }

    /**
     * Complete the order after payment verification
     */
    private function completeOrder(Order $order): bool
    {
        try {
            // Update order status to completed
            $order->status = Order::STATUS_COMPLETED;
            $order->updated_at = time();
            
            if (!$order->save()) {
                Log::error('UddoktaPay failed to save completed order status', [
                    'trade_no' => $order->trade_no
                ]);
                return false;
            }

            Log::info('UddoktaPay order marked as completed', [
                'trade_no' => $order->trade_no,
                'status' => $order->status
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('UddoktaPay order completion error', [
                'error' => $e->getMessage(),
                'trade_no' => $order->trade_no
            ]);
            return false;
        }
    }

    /**
     * Manual payment verification endpoint
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|string'
        ]);

        try {
            $invoiceId = $request->input('invoice_id');
            
            Log::info('UddoktaPay manual verification requested', [
                'invoice_id' => $invoiceId
            ]);
            
            // Use the UddoktaPay service to verify
            $uddoktaPayService = new UddoktaPayService();
            $result = $uddoktaPayService->verifyPayment($invoiceId);
            
            if (!$result) {
                Log::error('UddoktaPay manual verification failed', [
                    'invoice_id' => $invoiceId
                ]);
                return $this->fail([400, 'Payment verification failed']);
            }

            Log::info('UddoktaPay manual verification successful', [
                'invoice_id' => $invoiceId,
                'status' => $result['status'] ?? 'unknown'
            ]);

            return $this->success($result);

        } catch (\Exception $e) {
            Log::error('UddoktaPay manual verification error', [
                'error' => $e->getMessage(),
                'invoice_id' => $request->input('invoice_id')
            ]);
            return $this->fail([500, 'Verification error']);
        }
    }
} 