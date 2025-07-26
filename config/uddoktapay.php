<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UddoktaPay Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for UddoktaPay payment integration.
    |
    */

    // API Configuration
    'api_key' => env('UDDOKTAPAY_API_KEY', ''),
    
    // API Endpoints
    'api_base_url' => env('UDDOKTAPAY_API_BASE_URL', 'https://api.uddoktapay.com/api'),
    'sandbox_mode' => env('UDDOKTAPAY_SANDBOX_MODE', true),
    
    // Payment Settings
    'currency' => env('UDDOKTAPAY_CURRENCY', 'BDT'),
    'default_description' => 'Payment for Xboard service',
    
    // Webhook Settings
    'webhook_timeout' => env('UDDOKTAPAY_WEBHOOK_TIMEOUT', 30),
    'webhook_retry_attempts' => env('UDDOKTAPAY_WEBHOOK_RETRY_ATTEMPTS', 3),
    
    // Logging
    'log_webhooks' => env('UDDOKTAPAY_LOG_WEBHOOKS', true),
    'log_payments' => env('UDDOKTAPAY_LOG_PAYMENTS', true),
]; 