<?php

namespace App\Console\Commands;

use App\Models\OrderingBackRequest;
use App\Notifications\OrderingBack;
use App\Support\OrderRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Every few minutes: once ordering is no longer paused, email everyone who asked to be told.
 */
class NotifyOrderingBack extends Command
{
    protected $signature = 'ordering:notify-back';

    protected $description = 'Tell people who asked that ordering is back';

    public function handle(): int
    {
        if (OrderRules::pausedEntirely()) {
            return self::SUCCESS;
        }

        $count = 0;
        OrderingBackRequest::whereNull('notified_at')->orderBy('id')->limit(500)->get()->each(function (OrderingBackRequest $request) use (&$count) {
            Notification::route('mail', $request->email)->notify(new OrderingBack($request->locale));
            $request->forceFill(['notified_at' => now()])->save();
            $count++;
        });
        if ($count) {
            $this->info('Told '.$count.' people that ordering is back.');
        }

        return self::SUCCESS;
    }
}
