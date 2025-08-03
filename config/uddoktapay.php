<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UddoktaPay Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for UddoktaPay payment integration.
    | Official UddoktaPay documentation: https://uddoktapay.readme.io/reference
    |
    */

    // API Configuration
    'sandbox_mode' => env('UDDOKTAPAY_SANDBOX_MODE', true),
    
    // Sandbox Configuration
    'sandbox_api_key' => env('UDDOKTAPAY_SANDBOX_API_KEY', '982d381360a69d419689740d9f2e26ce36fb7a50'),
    
    // Live Configuration
    'live_api_key' => env('UDDOKTAPAY_LIVE_API_KEY', ''),
    'live_base_url' => env('UDDOKTAPAY_LIVE_BASE_URL', ''),
    
    // Payment Settings
    'currency' => env('UDDOKTAPAY_CURRENCY', 'BDT'),
    'default_description' => 'Payment for Xboard service',
    
    // Webhook Settings
    'webhook_timeout' => env('UDDOKTAPAY_WEBHOOK_TIMEOUT', 30),
    'webhook_retry_attempts' => env('UDDOKTAPAY_WEBHOOK_RETRY_ATTEMPTS', 3),
    
    // Logging
    'log_webhooks' => env('UDDOKTAPAY_LOG_WEBHOOKS', true),
    'log_payments' => env('UDDOKTAPAY_LOG_PAYMENTS', true),
    
    // Payment Types
    'payment_type' => env('UDDOKTAPAY_PAYMENT_TYPE', 'bangladeshi'), // 'bangladeshi' or 'global'
]; 