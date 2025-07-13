# API Integration Guide

## Overview
This guide covers external API integrations for the Iruali E-commerce application using GuzzleHTTP. The application includes integrations for WhatsApp Cloud API, BML payment gateway, SMS services, and other third-party APIs.

## Dependencies

### GuzzleHTTP
GuzzleHTTP is already installed and configured:
```bash
composer require guzzlehttp/guzzle
```

**Version:** 7.9.3 (Latest stable)

## Available Integrations

### 1. WhatsApp Cloud API
Send WhatsApp messages, OTP codes, and order notifications.

#### Configuration
Add to your `.env` file:
```env
WHATSAPP_TOKEN=your_whatsapp_access_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_webhook_verify_token
```

#### Usage Examples
```php
use App\Services\ApiService;

$apiService = new ApiService();

// Send OTP via WhatsApp
$result = $apiService->sendWhatsAppOTP('+9601234567', '123456');

// Send order confirmation
$orderData = [
    'order_number' => 'ORD-001',
    'total' => '150.00',
    'status' => 'Confirmed'
];
$result = $apiService->sendOrderConfirmationWhatsApp('+9601234567', $orderData);

// Send custom message
$result = $apiService->sendWhatsAppMessage('+9601234567', 'Hello from Iruali!');
```

### 2. BML Payment Gateway
Process online payments through Bank of Maldives.

#### Configuration
Add to your `.env` file:
```env
BML_BASE_URI=https://api.bml.com.mv
BML_API_KEY=your_bml_api_key
BML_MERCHANT_ID=your_merchant_id
BML_SECRET_KEY=your_secret_key
BML_ENVIRONMENT=sandbox
```

#### Usage Examples
```php
// Initialize payment
$paymentData = [
    'amount' => 150.00,
    'order_id' => 'ORD-001',
    'customer_email' => 'customer@example.com',
    'customer_phone' => '+9601234567',
];

$result = $apiService->initializeBMLPayment($paymentData);

// Verify payment status
$result = $apiService->verifyBMLPayment('transaction_id_here');
```

### 3. SMS Gateway
Send SMS messages for OTP and notifications.

#### Configuration
Add to your `.env` file:
```env
SMS_PROVIDER=twilio
SMS_API_KEY=your_sms_api_key
SMS_API_SECRET=your_sms_api_secret
SMS_FROM_NUMBER=your_sms_number
SMS_ENDPOINT=https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json
```

#### Usage Examples
```php
// Send SMS
$result = $apiService->sendSMS('+9601234567', 'Your OTP is: 123456');
```

### 4. Generic API Calls
Make any HTTP request with caching and error handling.

#### Usage Examples
```php
// GET request with caching
$result = $apiService->makeApiCall(
    'GET',
    'https://api.example.com/data',
    ['param' => 'value'],
    ['Authorization' => 'Bearer token'],
    60 // Cache for 60 minutes
);

// POST request
$result = $apiService->makeApiCall(
    'POST',
    'https://api.example.com/submit',
    ['data' => 'value'],
    ['Content-Type' => 'application/json']
);
```

## Testing API Integrations

### Test Command
Use the provided test command to verify API functionality:

```bash
# Test all services
php artisan api:test

# Test specific service
php artisan api:test whatsapp
php artisan api:test bml
php artisan api:test sms
php artisan api:test exchange
```

### Test Output Example
```
🧪 Testing External API Integrations
=====================================
Testing all available services...

📊 Testing Exchange Rates API...
✅ Exchange rates retrieved successfully!
Base currency: MVR
Last updated: 2025-01-13
Sample rates:
  USD: 0.065
  EUR: 0.060
  GBP: 0.052
  JPY: 9.85
  AUD: 0.098

🏥 Testing API Health Check...
❌ whatsapp: Unhealthy
❌ bml: Unhealthy

🌐 Testing Generic API Call...
✅ Generic API call successful!
Status Code: 200
Response contains: slideshow data
```

## Integration in Controllers

### Example: WhatsApp OTP in AuthController
```php
use App\Services\ApiService;

class AuthController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function sendSMSOTP(Request $request)
    {
        $phone = $request->phone;
        $otp = OTP::createForPhone($phone, 'verification');
        
        // Send via WhatsApp
        $result = $this->apiService->sendWhatsAppOTP($phone, $otp->code);
        
        if ($result['success']) {
            NotificationService::success('OTP sent to WhatsApp successfully!');
        } else {
            // Fallback to SMS
            $smsResult = $this->apiService->sendSMS($phone, "Your OTP is: {$otp->code}");
            if ($smsResult['success']) {
                NotificationService::success('OTP sent via SMS!');
            } else {
                NotificationService::error('Failed to send OTP. Please try again.');
            }
        }
        
        return redirect()->back();
    }
}
```

### Example: BML Payment in OrderController
```php
use App\Services\ApiService;

class OrderController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function processPayment(Request $request, Order $order)
    {
        $paymentData = [
            'amount' => $order->total,
            'order_id' => $order->order_number,
            'customer_email' => $order->user->email,
            'customer_phone' => $order->user->phone,
        ];

        $result = $this->apiService->initializeBMLPayment($paymentData);

        if ($result['success']) {
            // Redirect to BML payment page
            return redirect($result['data']['payment_url']);
        } else {
            NotificationService::error('Payment initialization failed: ' . $result['error']);
            return redirect()->back();
        }
    }
}
```

## Error Handling

The ApiService includes comprehensive error handling:

### Response Format
```php
// Success response
[
    'success' => true,
    'data' => $responseData,
    'status_code' => 200
]

// Error response
[
    'success' => false,
    'error' => 'Error message',
    'status_code' => 400
]
```

### Logging
All API calls are automatically logged:
- Success: `storage/logs/laravel.log`
- Errors: `storage/logs/laravel.log` with detailed error information

## Security Best Practices

### 1. Environment Variables
- Never commit API keys to version control
- Use `.env` files for all sensitive data
- Keep production credentials secure

### 2. Rate Limiting
- Implement rate limiting for API calls
- Use caching to reduce API requests
- Monitor API usage and costs

### 3. Error Handling
- Always check API responses
- Implement fallback mechanisms
- Log all API interactions

### 4. Validation
- Validate all input data before API calls
- Sanitize phone numbers and email addresses
- Verify API responses

## Production Checklist

- [ ] All API keys configured in `.env`
- [ ] Test all integrations with `php artisan api:test`
- [ ] Verify error handling and logging
- [ ] Set up monitoring for API health
- [ ] Configure rate limiting
- [ ] Test fallback mechanisms
- [ ] Review security settings
- [ ] Monitor API costs and usage

## Troubleshooting

### Common Issues

#### 1. WhatsApp API Errors
```
Error: Invalid phone number format
```
**Solution:** Ensure phone numbers include country code (+960 for Maldives)

#### 2. BML Payment Failures
```
Error: Invalid merchant credentials
```
**Solution:** Verify BML API key and merchant ID in production environment

#### 3. SMS Delivery Issues
```
Error: SMS not delivered
```
**Solution:** Check SMS provider configuration and account balance

### Debug Steps
1. Run `php artisan api:test` to identify issues
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify API credentials and configuration
4. Test with API provider's test environment first

## API Providers Setup

### WhatsApp Business API
1. Create Facebook Developer account
2. Set up WhatsApp Business app
3. Configure webhooks
4. Get access token and phone number ID

### BML Payment Gateway
1. Contact BML for merchant account
2. Get API credentials
3. Configure webhook URLs
4. Test in sandbox environment

### SMS Gateway (Twilio)
1. Create Twilio account
2. Get API credentials
3. Configure phone number
4. Set up webhook endpoints

## Support

For API integration issues:
1. Check this documentation
2. Review Laravel logs
3. Test with `php artisan api:test`
4. Contact API provider support
5. Check Laravel and GuzzleHTTP documentation 