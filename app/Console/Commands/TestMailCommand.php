<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email? : Email address to send test to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?: config('mail.from.address');
        
        $this->info('Testing email configuration...');
        $this->info('From: ' . config('mail.from.address') . ' (' . config('mail.from.name') . ')');
        $this->info('To: ' . $email);
        $this->info('Mailer: ' . config('mail.default'));
        $this->info('Host: ' . config('mail.mailers.smtp.host'));
        $this->info('Port: ' . config('mail.mailers.smtp.port'));
        $this->info('Encryption: ' . config('mail.mailers.smtp.encryption'));
        
        try {
            // Send test email
            Mail::raw('This is a test email from Iruali E-commerce to verify mail configuration is working properly.', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Iruali Mail Configuration Test')
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            $this->info('✅ Test email sent successfully!');
            $this->info('Check your inbox for the test email.');
            
            // Log successful test
            Log::info('Mail configuration test passed', [
                'to' => $email,
                'from' => config('mail.from.address'),
                'mailer' => config('mail.default')
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test email:');
            $this->error($e->getMessage());
            
            // Log the error
            Log::error('Mail configuration test failed', [
                'error' => $e->getMessage(),
                'to' => $email,
                'from' => config('mail.from.address'),
                'mailer' => config('mail.default')
            ]);
            
            $this->warn('Troubleshooting tips:');
            $this->warn('1. Check your SMTP credentials in .env');
            $this->warn('2. Verify SMTP host and port are correct');
            $this->warn('3. Ensure firewall allows outbound SMTP traffic');
            $this->warn('4. Check if your email provider requires app-specific passwords');
            
            return Command::FAILURE;
        }
    }
}
