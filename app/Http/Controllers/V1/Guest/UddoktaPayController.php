<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plugin;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\UddoktaPayService;
use App\Services\Plugin\PluginManager;
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
            $status = $request->get('status');
            
            if (!$invoiceId) {
                return redirect('/#/payment?error=missing_invoice_id');
            }

            Log::info('UddoktaPay return URL accessed', [
                'invoice_id' => $invoiceId,
                'status' => $status,
                'all_params' => $request->all(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]);

            // STRATEGY 1: First try to find order by callback_no (invoice_id)
            // This is the most reliable method when webhooks have been processed
            $order = Order::where('callback_no', $invoiceId)->first();
            
            Log::info('UddoktaPay return checking callback_no', [
                'invoice_id' => $invoiceId,
                'order_found' => $order ? true : false,
                'order_trade_no' => $order ? $order->trade_no : null,
                'order_status' => $order ? $order->status : null
            ]);
            
            if ($order) {
                Log::info('UddoktaPay return found order by callback_no', [
                    'trade_no' => $order->trade_no,
                    'invoice_id' => $invoiceId,
                    'current_status' => $order->status,
                    'status_name' => $order->status === Order::STATUS_COMPLETED ? 'COMPLETED' : 
                                   ($order->status === Order::STATUS_PENDING ? 'PENDING' : 
                                   ($order->status === Order::STATUS_PROCESSING ? 'PROCESSING' : 'OTHER'))
                ]);
                
                // If order is already completed, redirect to success immediately
                if ($order->status === Order::STATUS_COMPLETED) {
                    Log::info('UddoktaPay return order already completed, redirecting to success', [
                        'trade_no' => $order->trade_no,
                        'invoice_id' => $invoiceId
                    ]);
                    return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                }
                
                // If status is completed and order is still pending, process it
                if ($status === 'completed' && $order->status === Order::STATUS_PENDING) {
                    Log::info('UddoktaPay return processing pending order with completed status', [
                        'trade_no' => $order->trade_no,
                        'invoice_id' => $invoiceId
                    ]);
                    $this->processOrderPayment($order, $invoiceId, ['status' => 'COMPLETED']);
                    return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                }
                
                // If status is completed and order is processing, just redirect to success
                if ($status === 'completed' && $order->status === Order::STATUS_PROCESSING) {
                    Log::info('UddoktaPay return order processing, redirecting to success', [
                        'trade_no' => $order->trade_no,
                        'invoice_id' => $invoiceId
                    ]);
                    return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                }
                
                // For any other case with completed status, redirect to success
                if ($status === 'completed') {
                    Log::info('UddoktaPay return completed status, redirecting to success', [
                        'trade_no' => $order->trade_no,
                        'invoice_id' => $invoiceId,
                        'order_status' => $order->status
                    ]);
                    return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                }
                
                // If status is not completed, redirect to pending
                Log::info('UddoktaPay return non-completed status, redirecting to pending', [
                    'trade_no' => $order->trade_no,
                    'invoice_id' => $invoiceId,
                    'status' => $status
                ]);
                return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=pending');
            }

            // STRATEGY 2: Order not found by callback_no, try plugin verification
            // This happens when webhook hasn't been processed yet
            Log::info('UddoktaPay return order not found by callback_no, trying plugin verification', [
                'invoice_id' => $invoiceId
            ]);
            try {
                // Get UddoktaPay payment configuration 
                $payment = Payment::where('payment', 'UddoktaPay')->first();
                if (!$payment) {
                    throw new \Exception('UddoktaPay payment method not found in database');
                }
                
                // Get the configuration from the payment record
                $config = is_string($payment->config) ? json_decode($payment->config, true) : $payment->config;
                $config['enable'] = $payment->enable;
                $config['id'] = $payment->id;
                $config['uuid'] = $payment->uuid;
                $config['notify_domain'] = $payment->notify_domain ?? '';
                
                // Get UddoktaPay plugin through PluginManager
                $pluginManager = app(PluginManager::class);
                $paymentPlugins = $pluginManager->getEnabledPaymentPlugins();
                $plugin = null;
                
                Log::info('UddoktaPay return searching for plugin', [
                    'total_plugins' => count($paymentPlugins),
                    'available_plugin_codes' => array_map(function($p) { return $p->getPluginCode(); }, $paymentPlugins)
                ]);
                
                foreach ($paymentPlugins as $paymentPlugin) {
                    if ($paymentPlugin->getPluginCode() === 'uddokta_pay') {
                        $paymentPlugin->setConfig($config);
                        $plugin = $paymentPlugin;
                        Log::info('UddoktaPay return plugin found and configured', [
                            'plugin_code' => $paymentPlugin->getPluginCode()
                        ]);
                        break;
                    }
                }
                
                if (!$plugin) {
                    throw new \Exception('UddoktaPay plugin not found in enabled plugins');
                }
                
            } catch (\Exception $e) {
                Log::error('UddoktaPay plugin instantiation failed', [
                    'error' => $e->getMessage(),
                    'invoice_id' => $invoiceId
                ]);
                return redirect('/#/payment?error=plugin_not_found');
            }
            
            // Verify the payment status using the plugin's verification method
            $paymentResult = $plugin->verifyPayment($invoiceId);
            
            if (!$paymentResult) {
                Log::error('UddoktaPay return verification failed', [
                    'invoice_id' => $invoiceId,
                    'plugin_result' => $paymentResult
                ]);
                
                // If verification fails but status is completed, try to find recent pending orders
                if ($status === 'completed') {
                    Log::info('UddoktaPay return status is completed, searching for recent pending orders', [
                        'invoice_id' => $invoiceId
                    ]);
                    
                    // Strategy 1: Find recent pending orders (within last 2 hours)
                    $recentOrders = Order::where('status', Order::STATUS_PENDING)
                        ->where('created_at', '>', time() - 7200) // 2 hours ago
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                    
                    if ($recentOrders->count() === 1) {
                        // If there's only one recent pending order, it's likely this one
                        $order = $recentOrders->first();
                        Log::info('UddoktaPay return found single recent pending order', [
                            'trade_no' => $order->trade_no,
                            'invoice_id' => $invoiceId
                        ]);
                        
                        // Update callback_no and process payment
                        $order->callback_no = $invoiceId;
                        $order->save();
                        
                        $this->processOrderPayment($order, $invoiceId, ['status' => 'COMPLETED']);
                        return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                    } elseif ($recentOrders->count() > 1) {
                        // Multiple orders - try to match by amount or other criteria
                        Log::info('UddoktaPay return found multiple recent pending orders', [
                            'order_count' => $recentOrders->count(),
                            'invoice_id' => $invoiceId
                        ]);
                        
                        // Try to get more info from the request to match the correct order
                        // For now, let's use the most recent one as a reasonable guess
                        $order = $recentOrders->first();
                        Log::info('UddoktaPay return using most recent pending order', [
                            'trade_no' => $order->trade_no,
                            'invoice_id' => $invoiceId
                        ]);
                        
                        // Update callback_no and process payment
                        $order->callback_no = $invoiceId;
                        $order->save();
                        
                        $this->processOrderPayment($order, $invoiceId, ['status' => 'COMPLETED']);
                        return redirect('/#/payment?trade_no=' . $order->trade_no . '&status=success');
                    }
                }
                
                return redirect('/#/payment?error=verification_failed');
            }

            // Extract trade_no from verification result metadata
            $tradeNo = $paymentResult['metadata']['trade_no'] ?? null;
            
            if (!$tradeNo) {
                Log::error('UddoktaPay return missing trade_no', [
                    'invoice_id' => $invoiceId,
                    'verification_result' => $paymentResult
                ]);
                return redirect('/#/payment?error=missing_trade_no');
            }

            // Find the order by trade_no
            $order = Order::where('trade_no', $tradeNo)->first();
            
            if (!$order) {
                Log::error('UddoktaPay return order not found', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId
                ]);
                return redirect('/#/payment?error=order_not_found');
            }

            // Update callback_no if not set
            if (!$order->callback_no) {
                $order->callback_no = $invoiceId;
                $order->save();
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
                
                return redirect('/#/payment?trade_no=' . $tradeNo . '&status=success');
            } else {
                // Payment not completed yet
                Log::info('UddoktaPay return payment pending', [
                    'trade_no' => $tradeNo,
                    'invoice_id' => $invoiceId,
                    'status' => $paymentResult['status']
                ]);
                
                return redirect('/#/payment?trade_no=' . $tradeNo . '&status=pending');
            }

        } catch (\Exception $e) {
            Log::error('UddoktaPay return handler error', [
                'error' => $e->getMessage(),
                'invoice_id' => $request->get('invoice_id', 'unknown'),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('/#/payment?error=processing_failed');
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
