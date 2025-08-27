# PipraPay Payment Gateway Plugin

Official PipraPay payment gateway plugin for XBoard. This plugin integrates PipraPay's payment processing capabilities into your XBoard installation.

## Features

- ✅ Create charges/payments
- ✅ Verify payments via API  
- ✅ Handle webhooks (IPN) for automatic payment confirmation
- ✅ Support for both sandbox and live environments
- ✅ BDT and USD currency support
- ✅ Comprehensive logging and error handling
- ✅ Amount verification for security

## Configuration

### Required Settings

1. **Payment Mode**: Choose between `sandbox` (testing) or `live` (production)
2. **API Key**: Your PipraPay API key from the PipraPay dashboard
3. **Base URL**: PipraPay API base URL
   - Sandbox: `https://sandbox.piprapay.com`
   - Live: `https://api.piprapay.com` (or your custom domain)
4. **Currency**: Payment currency (`BDT` or `USD`)

### Optional Settings

- **Display Name**: Custom name for the payment method
- **Icon**: Custom emoji or icon for the payment method

## Setup Instructions

1. **Enable the Plugin**: 
   - Go to Admin Panel > Payment Settings
   - Find PipraPay in the available payment methods
   - Click to configure

2. **Configure Settings**:
   - Set your API key from PipraPay dashboard
   - Choose appropriate base URL for your environment
   - Select currency (BDT for Bangladesh, USD for international)
   - Enable the payment method

3. **Test the Integration**:
   - Use sandbox mode first to test payments
   - Verify webhook notifications are working
   - Check payment flow from checkout to completion

## API Endpoints

The plugin automatically handles these PipraPay API endpoints:

- **Create Charge**: `POST /api/create-charge`
- **Verify Payment**: `POST /api/verify-payments` 
- **Webhook Handling**: Processes incoming payment notifications

## Webhook Configuration

The plugin automatically provides webhook URLs for payment notifications:
- Webhook URL: `{your-domain}/api/v1/guest/payment/notify/PipraPay`

Make sure to configure this URL in your PipraPay dashboard.

## Security Features

- ✅ API key validation for webhooks
- ✅ Payment amount verification
- ✅ Order validation before processing
- ✅ Comprehensive logging for audit trails

## Currency Support

- **BDT**: Bangladeshi Taka (recommended for local customers)
- **USD**: US Dollar (for international customers)

## Error Handling

The plugin includes comprehensive error handling:
- API communication errors
- Invalid payment data
- Webhook verification failures
- Network timeouts and connectivity issues

All errors are logged for debugging and monitoring.

## Development Notes

This plugin is built following XBoard's plugin architecture:
- Extends `AbstractPlugin`
- Implements `PaymentInterface`
- Includes embedded PipraPay SDK for seamless integration
- Follows XBoard coding standards and patterns

## Support

For issues related to:
- **Plugin functionality**: Contact XBoard support
- **PipraPay API**: Contact PipraPay support
- **Integration help**: Check PipraPay documentation

## Version History

- **v1.0.0**: Initial release with full PipraPay integration
