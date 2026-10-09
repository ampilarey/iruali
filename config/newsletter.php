<?php

/*
|--------------------------------------------------------------------------
| Newsletter sending (Admin → Moderation → Newsletter)
|--------------------------------------------------------------------------
|
| A send is queued in batches. Shared hosting usually caps outgoing mail per hour (often a few
| hundred), so batches are small and spaced out: the defaults send at most 480 emails an hour.
| With a mail service that allows more (Amazon SES, Postmark, Mailgun...), raise them.
|
*/

return [

    // Emails per queued batch
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 40),

    // Minutes between one batch and the next
    'minutes_between_batches' => (int) env('NEWSLETTER_MINUTES_BETWEEN_BATCHES', 5),

];
