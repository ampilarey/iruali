<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ApiService
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Iruali-Ecommerce/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->config = [
            'whatsapp' => [
                'base_uri' => 'https://graph.facebook.com/v18.0/',
                'token' => config('services.whatsapp.token'),
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
            ],
            'bml' => [
                'base_uri' => config('services.bml.base_uri'),
                'api_key' => config('services.bml.api_key'),
                'merchant_id' => config('services.bml.merchant_id'),
            ],
        ];
    }

    /**
     * Send WhatsApp message using WhatsApp Cloud API
     */
    public function sendWhatsAppMessage(string $phoneNumber, string $message, array $options = [])
    {
        try {
            $endpoint = $this->config['whatsapp']['phone_number_id'] . '/messages';
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ];

            // Add template message support
            if (isset($options['template'])) {
                $data['type'] = 'template';
                $data['template'] = $options['template'];
            }

            $response = $this->client->post($this->config['whatsapp']['base_uri'] . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['whatsapp']['token'],
                ],
                'json' => $data,
            ]);

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                Log::info('WhatsApp message sent successfully', [
                    'phone' => $phoneNumber,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                ]);
                return ['success' => true, 'data' => $result];
            } else {
                Log::error('WhatsApp API error', [
                    'status' => $response->getStatusCode(),
                    'response' => $result,
                ]);
                return ['success' => false, 'error' => $result['error']['message'] ?? 'Unknown error'];
            }

        } catch (RequestException $e) {
            Log::error('WhatsApp API request failed', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp OTP message
     */
    public function sendWhatsAppOTP(string $phoneNumber, string $otpCode)
    {
        $message = "Your Iruali verification code is: {$otpCode}\n\nThis code will expire in 10 minutes.";
        
        return $this->sendWhatsAppMessage($phoneNumber, $message);
    }

    /**
     * Send order confirmation via WhatsApp
     */
    public function sendOrderConfirmationWhatsApp(string $phoneNumber, array $orderData)
    {
        $message = "🎉 Order Confirmed!\n\n";
        $message .= "Order #: {$orderData['order_number']}\n";
        $message .= "Total: MVR {$orderData['total']}\n";
        $message .= "Status: {$orderData['status']}\n\n";
        $message .= "Thank you for shopping with Iruali!";

        return $this->sendWhatsAppMessage($phoneNumber, $message);
    }

    /**
     * Initialize BML payment
     */
    public function initializeBMLPayment(array $paymentData)
    {
        try {
            $endpoint = '/api/payment/initiate';
            
            $data = [
                'merchantId' => $this->config['bml']['merchant_id'],
                'amount' => $paymentData['amount'],
                'currency' => 'MVR',
                'orderId' => $paymentData['order_id'],
                'customerEmail' => $paymentData['customer_email'],
                'customerPhone' => $paymentData['customer_phone'],
                'callbackUrl' => route('payment.bml.callback'),
                'returnUrl' => route('payment.bml.return'),
            ];

            $response = $this->client->post($this->config['bml']['base_uri'] . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['bml']['api_key'],
                ],
                'json' => $data,
            ]);

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                Log::info('BML payment initiated successfully', [
                    'order_id' => $paymentData['order_id'],
                    'transaction_id' => $result['transactionId'] ?? null,
                ]);
                return ['success' => true, 'data' => $result];
            } else {
                Log::error('BML API error', [
                    'status' => $response->getStatusCode(),
                    'response' => $result,
                ]);
                return ['success' => false, 'error' => $result['message'] ?? 'Payment initiation failed'];
            }

        } catch (RequestException $e) {
            Log::error('BML API request failed', [
                'error' => $e->getMessage(),
                'order_id' => $paymentData['order_id'] ?? null,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify BML payment status
     */
    public function verifyBMLPayment(string $transactionId)
    {
        try {
            $endpoint = "/api/payment/status/{$transactionId}";

            $response = $this->client->get($this->config['bml']['base_uri'] . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['bml']['api_key'],
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                return ['success' => true, 'data' => $result];
            } else {
                return ['success' => false, 'error' => $result['message'] ?? 'Payment verification failed'];
            }

        } catch (RequestException $e) {
            Log::error('BML payment verification failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generic API call with caching
     */
    public function makeApiCall(string $method, string $url, array $data = [], array $headers = [], int $cacheMinutes = 0)
    {
        $cacheKey = 'api_call_' . md5($method . $url . json_encode($data));

        // Return cached response if available
        if ($cacheMinutes > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $options = ['headers' => $headers];

            if (!empty($data)) {
                if ($method === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }
            }

            $response = $this->client->request($method, $url, $options);
            $result = json_decode($response->getBody(), true);

            $responseData = [
                'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                'status_code' => $response->getStatusCode(),
                'data' => $result,
            ];

            // Cache successful responses
            if ($cacheMinutes > 0 && $responseData['success']) {
                Cache::put($cacheKey, $responseData, $cacheMinutes * 60);
            }

            return $responseData;

        } catch (RequestException $e) {
            Log::error('API call failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Get exchange rates (example API call)
     */
    public function getExchangeRates()
    {
        return $this->makeApiCall(
            'GET',
            'https://api.exchangerate-api.com/v4/latest/MVR',
            [],
            [],
            60 // Cache for 1 hour
        );
    }

    /**
     * Send SMS via external SMS gateway
     */
    public function sendSMS(string $phoneNumber, string $message)
    {
        // Example SMS gateway integration
        $smsConfig = config('services.sms');
        
        return $this->makeApiCall('POST', $smsConfig['endpoint'], [
            'to' => $phoneNumber,
            'message' => $message,
            'api_key' => $smsConfig['api_key'],
        ], [
            'Authorization' => 'Bearer ' . $smsConfig['api_key'],
        ]);
    }

    /**
     * Health check for external APIs
     */
    public function healthCheck()
    {
        $results = [];

        // Check WhatsApp API
        try {
            $response = $this->client->get($this->config['whatsapp']['base_uri'] . $this->config['whatsapp']['phone_number_id'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['whatsapp']['token'],
                ],
            ]);
            $results['whatsapp'] = $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $results['whatsapp'] = false;
        }

        // Check BML API
        try {
            $response = $this->client->get($this->config['bml']['base_uri'] . '/health');
            $results['bml'] = $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $results['bml'] = false;
        }

        return $results;
    }
} 