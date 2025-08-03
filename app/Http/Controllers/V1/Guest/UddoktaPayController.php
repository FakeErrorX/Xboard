<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\UddoktaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Plugin\HookManager;

class UddoktaPayController extends Controller
{
    /**
     * Handle UddoktaPay return URL with invoice_id parameter
     * This handles cases where users return from payment page before webhook is processed
     */
    public function handleReturn(Request $request)
    {
        try {
            $invoiceId = $request->get('invoice_id');
            
            if (!$invoiceId) {
                return redirect('/#/order?error=missing_invoice_id');
            }

            Log::info('UddoktaPay return URL accessed', [
                'invoice_id' => $invoiceId,
                'all_params' => $request->all()
            ]);

            // Initialize UddoktaPay service
            $uddoktaPayService = new UddoktaPayService();
            
            // Verify the payment status using the invoice ID
            $paymentResult = $uddoktaPayService->verifyPayment($invoiceId);
            
            if (!$paymentResult['success']) {
                Log::error('UddoktaPay return verification failed', [
                    'invoice_id' => $invoiceId,
                    'error' => $paymentResult['message'] ?? 'Unknown error'
                ]);
                return redirect('/#/order?error=verification_failed');
            }

            // Extract trade_no from verification result
            $tradeNo = $paymentResult['metadata']['trade_no'] ?? null;
            
            if (!$tradeNo) {
                Log::error('UddoktaPay return missing trade_no', [
                    'invoice_id' => $invoiceId,
                    'verification_result' => $paymentResult
                ]);
                return redirect('/#/order?error=missing_trade_no');
            }

            // Find the order
            $order = Order::where('trade_no', $tradeNo)->first();
            
            if (!$order) {
                Log::error('UddoktaPay return order not found', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId
                ]);
                return redirect('/#/order?error=order_not_found');
            }

            // Check if payment is completed
            if ($paymentResult['status'] === 'COMPLETED') {
                // Process payment if not already processed
                if ($order->status === Order::STATUS_PENDING) {
                    $this->processOrderPayment($order, $invoiceId, $paymentResult);
                }
                
                // Redirect to order success page
                Log::info('UddoktaPay return payment completed', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId
                ]);
                
                return redirect('/#/order/' . $tradeNo . '?status=success');
            } else {
                // Payment not completed yet
                Log::info('UddoktaPay return payment pending', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId,
                    'status' => $paymentResult['status']
                ]);
                
                return redirect('/#/order/' . $tradeNo . '?status=pending');
            }

        } catch (\Exception $e) {
            Log::error('UddoktaPay return handler error', [
                'error' => $e->getMessage(),
                'invoice_id' => $request->get('invoice_id', 'unknown')
            ]);
            return redirect('/#/order?error=processing_failed');
        }
    }

    /**
     * Handle UddoktaPay webhook notifications
     * This follows the official UddoktaPay webhook validation documentation
     * https://uddoktapay.readme.io/reference/validate-webhook
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('UddoktaPay webhook received', [
                'headers' => $request->headers->all(),
                'content_length' => strlen($request->getContent())
            ]);

            // Get raw request data
            $rawData = $request->getContent();
            
            // Parse JSON data
            $webhookData = json_decode($rawData, true);
            
            if ($webhookData === null) {
                Log::error('UddoktaPay webhook invalid JSON data', [
                    'raw_data' => substr($rawData, 0, 500) // Log first 500 chars
                ]);
                return response()->json(['error' => 'Invalid JSON data'], 400);
            }

            // Initialize UddoktaPay service
            $uddoktaPayService = new UddoktaPayService();
            
            // Validate webhook authenticity
            if (!$uddoktaPayService->validateWebhook($request->headers->all(), $webhookData)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Process webhook payload
            $result = $uddoktaPayService->processWebhook($webhookData);
            
            if (!$result['success']) {
                Log::error('UddoktaPay webhook processing failed', [
                    'error' => $result['message'],
                    'webhook_data' => $webhookData
                ]);
                return response()->json(['error' => $result['message']], 400);
            }

            // Skip if already processed
            if (isset($result['already_processed']) && $result['already_processed']) {
                Log::info('UddoktaPay webhook order already processed', [
                    'invoice_id' => $result['invoice_id'] ?? 'unknown'
                ]);
                return response()->json(['status' => 'already_processed'], 200);
            }

            // Only process completed payments
            if ($result['status'] !== 'COMPLETED') {
                Log::info('UddoktaPay webhook payment not completed', [
                    'status' => $result['status'],
                    'invoice_id' => $result['invoice_id']
                ]);
                return response()->json(['status' => 'payment_not_completed'], 200);
            }

            // Process the order payment
            $order = $result['order'];
            if (!$this->processOrderPayment($order, $result['invoice_id'], $webhookData)) {
                return response()->json(['error' => 'Order processing failed'], 500);
            }

            Log::info('UddoktaPay webhook processed successfully', [
                'trade_no' => $order->trade_no,
                'invoice_id' => $result['invoice_id']
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('UddoktaPay webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process order payment after webhook validation
     */
    private function processOrderPayment(Order $order, string $invoiceId, array $webhookData): bool
    {
        try {
            // Check if order is already processed
            if ($order->status !== Order::STATUS_PENDING) {
                Log::info('UddoktaPay order already processed', [
                    'trade_no' => $order->trade_no,
                    'current_status' => $order->status
                ]);
                return true;
            }

            // Mark order as paid using OrderService
            $orderService = new OrderService($order);
            
            if (!$orderService->paid($invoiceId)) {
                Log::error('UddoktaPay order payment marking failed', [
                    'trade_no' => $order->trade_no,
                    'invoice_id' => $invoiceId
                ]);
                return false;
            }

            // Log successful payment processing
            Log::info('UddoktaPay order payment processed successfully', [
                'trade_no' => $order->trade_no,
                'invoice_id' => $invoiceId,
                'amount' => $webhookData['amount'] ?? 0,
                'payment_method' => $webhookData['payment_method'] ?? 'unknown',
                'transaction_id' => $webhookData['transaction_id'] ?? '',
                'sender_number' => $webhookData['sender_number'] ?? ''
            ]);

            // Trigger success hook
            HookManager::call('payment.notify.success', $order);
            
            return true;

        } catch (\Exception $e) {
            Log::error('UddoktaPay order payment processing error', [
                'error' => $e->getMessage(),
                'trade_no' => $order->trade_no,
                'invoice_id' => $invoiceId
            ]);
            return false;
        }
    }

    /**
     * Manual payment verification endpoint
     * This can be used by administrators to manually verify payments
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
            
            // Initialize UddoktaPay service
            $uddoktaPayService = new UddoktaPayService();
            
            // Verify payment with UddoktaPay API
            $result = $uddoktaPayService->verifyPayment($invoiceId);
            
            if (!$result['success']) {
                Log::error('UddoktaPay manual verification failed', [
                    'invoice_id' => $invoiceId,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
                return $this->fail([400, 'Payment verification failed: ' . ($result['message'] ?? 'Unknown error')]);
            }

            Log::info('UddoktaPay manual verification successful', [
                'invoice_id' => $invoiceId,
                'status' => $result['status'],
                'amount' => $result['amount']
            ]);

            return $this->success([
                'status' => $result['status'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'payment_method' => $result['payment_method'],
                'transaction_id' => $result['transaction_id'],
                'metadata' => $result['metadata']
            ]);

        } catch (\Exception $e) {
            Log::error('UddoktaPay manual verification error', [
                'error' => $e->getMessage(),
                'invoice_id' => $request->input('invoice_id')
            ]);
            return $this->fail([500, 'Verification error: ' . $e->getMessage()]);
        }
    }
}
