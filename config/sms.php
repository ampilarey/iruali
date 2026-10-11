<?php

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
|
| Text messages to customers (verification codes, order updates, replies from a shop).
|
| driver: "log" writes the message to the log and the sms_messages table without sending
| anything (the default: nothing is sent until a gateway is configured); "http" calls a
| bulk-SMS style HTTP gateway (Dhiraagu, Ooredoo and resellers all offer one) without a
| vendor SDK: a URL, a method, an auth header, a body template and a regex that tells a
| successful reply apart.
|
*/

return [

    'driver' => env('SMS_DRIVER', 'log'),

    // Shown as the sender when the gateway allows an alphanumeric sender id
    'sender_id' => env('SMS_SENDER_ID', 'iruali'),

    'http' => [
        'url' => env('SMS_URL'),

        // GET or POST
        'method' => strtoupper((string) env('SMS_METHOD', 'POST')),

        // A whole header line, e.g. "Authorization: Bearer xxx" or "X-Api-Key: xxx"
        'auth_header' => env('SMS_AUTH_HEADER'),

        // The request body (POST) or query string (GET) with {to}, {message} and {sender}
        // placeholders. {to} is "+9607771234"; {to_plain} is "9607771234" and {to_local}
        // "7771234" for gateways that want those. A template that starts with "{" is sent as
        // JSON; anything else is sent as form fields / query parameters, e.g.
        // "to={to}&text={message}&from={sender}". Left blank, the gateway gets to, message
        // and sender as plain parameters.
        //
        // Dhiraagu (messaging.dhiraagu.com.mv), the same call Akuru makes, with KEY = base64 of
        // "username:password" from the Dhiraagu Bulk SMS portal:
        //   SMS_URL=https://messaging.dhiraagu.com.mv/v1/api/sms
        //   SMS_BODY_TEMPLATE='{"destination":["{to_plain}"],"content":"{message}","authorizationKey":"KEY"}'
        //   SMS_SUCCESS_REGEX='"transactionStatus"\s*:\s*"true"'
        // Add "source":"{sender}" only when Dhiraagu has approved SMS_SENDER_ID for the account
        // (without it the account's default sender name is used). Try one: php artisan sms:test 7771234
        'body_template' => env('SMS_BODY_TEMPLATE'),

        // Matched against the response body to decide the message was accepted; left blank,
        // any 2xx response counts as success.
        'success_regex' => env('SMS_SUCCESS_REGEX'),

        'timeout' => 10,
    ],

];
