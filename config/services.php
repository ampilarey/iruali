<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure WhatsApp Business API for sending messages, OTP, and notifications
    |
    */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | BML Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Bank of Maldives payment gateway for online payments
    |
    */
    'bml' => [
        // BML Connect (Bank of Maldives payment gateway). Create an app in the BML merchant portal
        // to get the API key. Use the UAT (sandbox) environment until BML approves the go-live.
        'api_key' => env('BML_API_KEY'),
        'environment' => env('BML_ENVIRONMENT', 'sandbox'), // sandbox or production
        'base_uri' => env('BML_BASE_URI') ?: (env('BML_ENVIRONMENT', 'sandbox') === 'production'
            ? 'https://api.merchants.bankofmaldives.com.mv/public'
            : 'https://api.uat.merchants.bankofmaldives.com.mv/public'),
        'merchant_id' => env('BML_MERCHANT_ID'),
        'app_id' => env('BML_APP_ID'),

        // Authorization header, as used by the Bake & Grill and Akuru live setups:
        //   raw          → {API_KEY}                  (BML UAT)
        //   bearer_jwt   → Bearer {API_KEY}
        //   bearer_basic → Bearer base64(API_KEY:APP_ID)
        //   auto         → Bearer for a JWT key (eyJ…), raw otherwise
        'auth_mode' => env('BML_AUTH_MODE', 'auto'),

        // Webhook signature. BML signs either with HMAC-SHA256 of the body using the portal's
        // webhook secret (X-BML-Signature), or with X-Signature = sha256(nonce + timestamp + API key).
        // Both are accepted; the payment itself is always re-checked with BML's API.
        'webhook_secret' => env('BML_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('BML_WEBHOOK_SIGNATURE_HEADER', 'X-BML-Signature'),

        // Sent as paymentPortalExperience.externalWebsiteTermsUrl (BML v2 asks for it).
        'terms_url' => env('BML_EXTERNAL_TERMS_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SMS gateway for sending OTP and notifications
    |
    */
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'twilio'), // twilio, nexmo, etc.
        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'from_number' => env('SMS_FROM_NUMBER'),
        'endpoint' => env('SMS_ENDPOINT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft OneDrive Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Microsoft Graph API for OneDrive backup integration
    | Get these credentials from Azure Portal: https://portal.azure.com/
    |
    */
    'onedrive' => [
        'client_id' => env('ONEDRIVE_CLIENT_ID'),
        'client_secret' => env('ONEDRIVE_CLIENT_SECRET'),
        'refresh_token' => env('ONEDRIVE_REFRESH_TOKEN'),
        'tenant_id' => env('ONEDRIVE_TENANT_ID'),
        'redirect_uri' => env('ONEDRIVE_REDIRECT_URI', 'http://localhost:8000/auth/onedrive/callback'),
    ],

];
