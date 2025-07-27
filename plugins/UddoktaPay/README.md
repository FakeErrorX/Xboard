# UddoktaPay Plugin for Xboard

A payment gateway plugin for Xboard that integrates with UddoktaPay payment automation service. This implementation **fully follows the official UddoktaPay API documentation**.

## Features

- ✅ Payment creation using UddoktaPay Create Charge API
- ✅ Payment verification using UddoktaPay Verify Payment API
- ✅ **Webhook validation following official UddoktaPay documentation**
- ✅ Sandbox mode support for testing
- ✅ Multiple currency support
- ✅ Comprehensive logging
- ✅ Error handling and validation

## Installation

1. Copy the `UddoktaPay` folder to your `plugins/` directory
2. Enable the plugin in your Xboard admin panel
3. Configure the plugin settings

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# UddoktaPay Configuration
UDDOKTAPAY_API_KEY=your_api_key_here
UDDOKTAPAY_API_BASE_URL=https://sandbox.uddoktapay.com/
UDDOKTAPAY_SANDBOX_MODE=true
UDDOKTAPAY_CURRENCY=BDT
```

### Sandbox Testing

For testing purposes, use the official UddoktaPay sandbox:

- **Sandbox API URL**: `https://sandbox.uddoktapay.com/api/checkout-v2`
- **Sandbox API Key**: `982d381360a69d419689740d9f2e26ce36fb7a50` (for testing only)

> **Note**: The sandbox API key is provided by UddoktaPay for testing purposes. For production, use your own API key from the UddoktaPay dashboard.

### Admin Panel Configuration

1. Go to Admin Panel → Payment Methods
2. Find "UddoktaPay" and click "Edit"
3. Configure the following settings:
   - **API Key**: Your UddoktaPay API key (use sandbox key for testing)
   - **Sandbox Mode**: Enable for testing, disable for production
   - **Currency**: Payment currency (e.g., BDT, USD)

## How It Works

### Payment Creation

1. User selects UddoktaPay as payment method
2. System creates payment via UddoktaPay Create Charge API
3. User is redirected to UddoktaPay payment page
4. User completes payment on UddoktaPay

### Payment Verification

**Two methods are supported:**

#### Method 1: Webhook (Recommended)
1. UddoktaPay sends webhook to your server with full payment data
2. System validates webhook using API key authentication
3. System processes the webhook data and marks order as completed
4. Order status is updated to "completed"

#### Method 2: Redirect with API Verification
1. User is redirected back to your site with `invoice_id` parameter
2. System calls UddoktaPay Verify Payment API with the `invoice_id`
3. System verifies payment status and processes the order
4. Order status is updated to "completed"

## API Endpoints

### Webhook Endpoint
- **URL**: `/api/v1/guest/uddoktapay/webhook`
- **Method**: POST
- **Headers**: `RT-UDDOKTAPAY-API-KEY` (for authentication)
- **Purpose**: Receives webhook notifications from UddoktaPay with full payment data

### Manual Verification Endpoint
- **URL**: `/api/v1/guest/uddoktapay/verify`
- **Method**: POST
- **Parameters**: `invoice_id`
- **Purpose**: Manually verify payment status via API

## API Integration

### Create Charge API
Based on [UddoktaPay Create Charge API](https://uddoktapay.readme.io/reference/create-charge-api-guideline):

```php
// Request to UddoktaPay (Sandbox)
POST https://sandbox.uddoktapay.com/api/checkout-v2
Headers:
  RT-UDDOKTAPAY-API-KEY: 982d381360a69d419689740d9f2e26ce36fb7a50
  Content-Type: application/json

Body:
{
  "full_name": "John Doe",
  "email": "customer@example.com",
  "amount": "100",
  "metadata": {
    "trade_no": "ORDER123",
    "user_id": "1"
  },
  "redirect_url": "https://your-domain.com/success",
  "cancel_url": "https://your-domain.com/cancel",
  "webhook_url": "https://your-domain.com/api/v1/guest/uddoktapay/webhook"
}
```

### Verify Payment API
Based on [UddoktaPay Verify Payment API](https://uddoktapay.readme.io/reference/verify-payment-api-guideline):

```php
// Verify payment status (Sandbox)
POST https://sandbox.uddoktapay.com/api/verify-payment
Headers:
  RT-UDDOKTAPAY-API-KEY: 982d381360a69d419689740d9f2e26ce36fb7a50
  Content-Type: application/json

Body:
{
  "invoice_id": "Erm9wzjM0FBwjSYT0QVb"
}
```

### Webhook Validation
Based on [UddoktaPay Validate Webhook](https://uddoktapay.readme.io/reference/validate-webhook):

```php
// Webhook validation
POST /api/v1/guest/uddoktapay/webhook
Headers:
  RT-UDDOKTAPAY-API-KEY: 982d381360a69d419689740d9f2e26ce36fb7a50

Body:
{
  "full_name": "John Doe",
  "email": "customer@example.com",
  "amount": "100.00",
  "fee": "0.00",
  "charged_amount": "100.00",
  "invoice_id": "Erm9wzjM0FBwjSYT0QVb",
  "metadata": {
    "trade_no": "ORDER123",
    "user_id": "1"
  },
  "payment_method": "bkash",
  "sender_number": "01311111111",
  "transaction_id": "TESTTRANS1",
  "date": "2023-01-07 14:00:50",
  "status": "COMPLETED"
}
```

## Security

### API Authentication
- Uses API key for all API requests
- API key is included in header: `RT-UDDOKTAPAY-API-KEY`

### Webhook Validation
- **API Key Authentication**: Webhooks are validated using the `RT-UDDOKTAPAY-API-KEY` header
- **JSON Validation**: Ensures webhook payload is valid JSON
- **Data Validation**: Validates required fields (invoice_id, status, metadata)
- **Status Verification**: Only processes webhooks with `status: "COMPLETED"`

## Error Handling

The plugin includes comprehensive error handling:

- **API Errors**: Logged with full response details
- **Webhook Errors**: Invalid JSON, missing data, authentication failures
- **Payment Errors**: Failed verifications, incomplete payments
- **Configuration Errors**: Missing API key, invalid settings
- **Network Errors**: Timeout and connection errors handled

## Logging

All activities are logged with appropriate levels:

- **Info**: Successful payments, webhook processing
- **Warning**: Non-critical issues, pending payments
- **Error**: Failed payments, verification errors, configuration issues

Logs can be found in your Laravel log files.

## Testing

### Sandbox Mode
1. Enable sandbox mode in plugin settings
2. Use test API credentials from UddoktaPay
3. Test payment flow end-to-end
4. Verify webhook processing

### Production Testing
1. Disable sandbox mode
2. Use live API credentials
3. Test with small amounts first
4. Monitor logs for any issues

## Troubleshooting

### Common Issues

**Webhook not received**
- Check webhook URL configuration in UddoktaPay dashboard
- Verify server is accessible from internet
- Check firewall settings

**Webhook authentication failed**
- Verify API key is correct
- Check webhook URL is properly configured
- Ensure `RT-UDDOKTAPAY-API-KEY` header is being sent

**Payment verification failed**
- Verify `invoice_id` is valid
- Check API key permissions
- Ensure payment was actually completed

**Order not updated**
- Check order exists with correct trade number
- Verify order status is pending
- Check database connection

### Debug Mode

Enable debug logging by setting:
```env
UDDOKTAPAY_LOG_WEBHOOKS=true
UDDOKTAPAY_LOG_PAYMENTS=true
```

## Support

For issues with this plugin:
1. Check the logs for error details
2. Verify configuration settings
3. Test with sandbox mode first
4. Contact Xboard support if needed

For UddoktaPay API issues:
- Refer to [UddoktaPay API Documentation](https://uddoktapay.readme.io/)
- Contact UddoktaPay support

## Version History

- **v1.0.0**: Initial release with Create Charge and Verify Payment API integration
- **v1.1.0**: Added proper webhook validation following official UddoktaPay documentation 