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
        // placeholders. A template that starts with "{" is sent as JSON; anything else is
        // sent as form fields / query parameters, e.g. "to={to}&text={message}&from={sender}".
        // Left blank, the gateway gets to, message and sender as plain parameters.
        'body_template' => env('SMS_BODY_TEMPLATE'),

        // Matched against the response body to decide the message was accepted; left blank,
        // any 2xx response counts as success.
        'success_regex' => env('SMS_SUCCESS_REGEX'),

        'timeout' => 10,
    ],

];
