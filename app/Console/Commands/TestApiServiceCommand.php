<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiService;

class TestApiServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test {service? : Service to test (whatsapp, bml, sms, exchange)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test external API integrations using GuzzleHTTP';

    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = $this->argument('service');

        $this->info('🧪 Testing External API Integrations');
        $this->info('=====================================');

        if (!$service) {
            $this->testAllServices();
        } else {
            $this->testSpecificService($service);
        }
    }

    protected function testAllServices()
    {
        $this->info('Testing all available services...');
        
        // Test exchange rates (public API, no credentials needed)
        $this->testExchangeRates();
        
        // Test health check
        $this->testHealthCheck();
        
        // Test generic API call
        $this->testGenericApiCall();
    }

    protected function testSpecificService($service)
    {
        switch ($service) {
            case 'whatsapp':
                $this->testWhatsApp();
                break;
            case 'bml':
                $this->testBML();
                break;
            case 'sms':
                $this->testSMS();
                break;
            case 'exchange':
                $this->testExchangeRates();
                break;
            default:
                $this->error("Unknown service: {$service}");
                $this->info('Available services: whatsapp, bml, sms, exchange');
        }
    }

    protected function testExchangeRates()
    {
        $this->info('📊 Testing Exchange Rates API...');
        
        $result = $this->apiService->getExchangeRates();
        
        if ($result['success']) {
            $this->info('✅ Exchange rates retrieved successfully!');
            $this->info('Base currency: ' . ($result['data']['base'] ?? 'Unknown'));
            $this->info('Last updated: ' . ($result['data']['date'] ?? 'Unknown'));
            
            if (isset($result['data']['rates'])) {
                $this->info('Sample rates:');
                $rates = array_slice($result['data']['rates'], 0, 5, true);
                foreach ($rates as $currency => $rate) {
                    $this->line("  {$currency}: {$rate}");
                }
            }
        } else {
            $this->error('❌ Failed to get exchange rates: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        $this->newLine();
    }

    protected function testWhatsApp()
    {
        $this->info('📱 Testing WhatsApp API...');
        
        if (!config('services.whatsapp.token')) {
            $this->warn('⚠️  WhatsApp token not configured. Skipping test.');
            $this->info('Add WHATSAPP_TOKEN to your .env file to test WhatsApp integration.');
            return;
        }

        $testPhone = $this->ask('Enter test phone number (with country code, e.g., +9601234567):');
        
        if (!$testPhone) {
            $this->warn('No phone number provided. Skipping WhatsApp test.');
            return;
        }

        $result = $this->apiService->sendWhatsAppOTP($testPhone, '123456');
        
        if ($result['success']) {
            $this->info('✅ WhatsApp OTP sent successfully!');
            $this->info('Message ID: ' . ($result['data']['messages'][0]['id'] ?? 'Unknown'));
        } else {
            $this->error('❌ Failed to send WhatsApp message: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        $this->newLine();
    }

    protected function testBML()
    {
        $this->info('🏦 Testing BML Payment API...');
        
        if (!config('services.bml.api_key')) {
            $this->warn('⚠️  BML API key not configured. Skipping test.');
            $this->info('Add BML_API_KEY to your .env file to test BML integration.');
            return;
        }

        $this->info('Testing BML payment initialization...');
        
        $paymentData = [
            'amount' => 100.00,
            'order_id' => 'TEST_' . time(),
            'customer_email' => 'test@example.com',
            'customer_phone' => '+9601234567',
        ];

        $result = $this->apiService->initializeBMLPayment($paymentData);
        
        if ($result['success']) {
            $this->info('✅ BML payment initialized successfully!');
            $this->info('Transaction ID: ' . ($result['data']['transactionId'] ?? 'Unknown'));
        } else {
            $this->error('❌ Failed to initialize BML payment: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        $this->newLine();
    }

    protected function testSMS()
    {
        $this->info('📨 Testing SMS Gateway...');
        
        if (!config('services.sms.api_key')) {
            $this->warn('⚠️  SMS API key not configured. Skipping test.');
            $this->info('Add SMS_API_KEY to your .env file to test SMS integration.');
            return;
        }

        $testPhone = $this->ask('Enter test phone number:');
        
        if (!$testPhone) {
            $this->warn('No phone number provided. Skipping SMS test.');
            return;
        }

        $result = $this->apiService->sendSMS($testPhone, 'Test SMS from Iruali E-commerce');
        
        if ($result['success']) {
            $this->info('✅ SMS sent successfully!');
        } else {
            $this->error('❌ Failed to send SMS: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        $this->newLine();
    }

    protected function testHealthCheck()
    {
        $this->info('🏥 Testing API Health Check...');
        
        $results = $this->apiService->healthCheck();
        
        foreach ($results as $service => $status) {
            $icon = $status ? '✅' : '❌';
            $this->info("{$icon} {$service}: " . ($status ? 'Healthy' : 'Unhealthy'));
        }
        
        $this->newLine();
    }

    protected function testGenericApiCall()
    {
        $this->info('🌐 Testing Generic API Call...');
        
        // Test a simple public API
        $result = $this->apiService->makeApiCall(
            'GET',
            'https://httpbin.org/json',
            [],
            [],
            5 // Cache for 5 minutes
        );
        
        if ($result['success']) {
            $this->info('✅ Generic API call successful!');
            $this->info('Status Code: ' . $result['status_code']);
            $this->info('Response contains: ' . (isset($result['data']['slideshow']) ? 'slideshow data' : 'other data'));
        } else {
            $this->error('❌ Generic API call failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        $this->newLine();
    }
}
