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
        'base_uri' => env('BML_BASE_URI', 'https://api.bml.com.mv'),
        'api_key' => env('BML_API_KEY'),
        'merchant_id' => env('BML_MERCHANT_ID'),
        'secret_key' => env('BML_SECRET_KEY'),
        'environment' => env('BML_ENVIRONMENT', 'sandbox'), // sandbox or production
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

];
