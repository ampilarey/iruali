<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generate the VAPID key pair for browser push notifications and store it in .env.
 */
class PushVapidCommand extends Command
{
    protected $signature = 'push:vapid
                            {--force : Replace keys that are already set (existing subscriptions stop working)}
                            {--file= : The env file to write (default: the app\'s .env)}
                            {--subject= : Contact for the push services, e.g. mailto:hello@iruali.mv}';

    protected $description = 'Generate VAPID keys for web push and write them to .env';

    public function handle(): int
    {
        $file = $this->option('file') ?: base_path('.env');
        if (! is_file($file)) {
            $this->error("No env file at {$file}.");

            return self::FAILURE;
        }

        $env = file_get_contents($file);
        if (preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $env) && ! $this->option('force')) {
            $this->warn('VAPID keys are already set. Use --force to replace them (browsers subscribed with the old key will stop getting notifications).');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        $subject = $this->option('subject') ?: config('webpush.subject') ?: 'mailto:'.config('mail.from.address');
        $values = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
            'VAPID_SUBJECT' => $subject,
        ];

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $env = preg_match('/^'.$key.'=.*$/m', $env)
                ? preg_replace('/^'.$key.'=.*$/m', $line, $env)
                : rtrim($env, "\n")."\n".$line."\n";
        }
        file_put_contents($file, $env);

        $this->info('VAPID keys written to '.$file.'.');
        $this->line('Public key: '.$keys['publicKey']);
        $this->line('Run `php artisan config:cache` on the server if config is cached.');

        return self::SUCCESS;
    }
}
