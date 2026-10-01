<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web push (browser notifications for order updates)
    |--------------------------------------------------------------------------
    |
    | VAPID keys identify the site to the browsers' push services. Generate them
    | once with `php artisan push:vapid` (writes them to .env). Without a public
    | key the "Get order updates on this device" button is hidden and the webpush
    | notification channel stays off.
    |
    */

    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hello@iruali.mv')),

];
