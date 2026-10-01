<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A generic HTTP bulk-SMS gateway (Dhiraagu / Ooredoo style): one request per message, the
 * body built from a template, success decided by the HTTP status and an optional regex.
 */
class HttpSms implements SmsDriver
{
    public function __construct(protected array $config) {}

    public function send(string $to, string $message, string $sender): SmsResult
    {
        $url = (string) ($this->config['url'] ?? '');
        if ($url === '') {
            return SmsResult::failed('SMS_URL is not set');
        }

        $values = ['to' => $to, 'message' => $message, 'sender' => $sender];
        $method = ($this->config['method'] ?? 'POST') === 'GET' ? 'GET' : 'POST';
        $template = trim((string) ($this->config['body_template'] ?? ''));

        try {
            $request = $this->request();

            if ($template !== '' && str_starts_with($template, '{')) {
                $body = json_decode($this->fill($template, $values, fn ($v) => substr(json_encode($v, JSON_UNESCAPED_UNICODE), 1, -1)), true);
                if (! is_array($body)) {
                    return SmsResult::failed('SMS_BODY_TEMPLATE is not valid JSON');
                }
                $response = $method === 'GET' ? $request->get($url, $body) : $request->post($url, $body);
            } else {
                $params = $values;
                if ($template !== '') {
                    parse_str($this->fill($template, $values, 'rawurlencode'), $params);
                }
                $response = $method === 'GET' ? $request->get($url, $params) : $request->asForm()->post($url, $params);
            }
        } catch (Throwable $e) {
            return SmsResult::failed(mb_substr(get_class($e).': '.$e->getMessage(), 0, 1000));
        }

        $body = mb_substr((string) $response->body(), 0, 2000);
        $regex = (string) ($this->config['success_regex'] ?? '');

        if (! $response->successful()) {
            return SmsResult::failed('HTTP '.$response->status().': '.$body);
        }
        if ($regex !== '' && ! @preg_match('/'.str_replace('/', '\/', $regex).'/u', $body)) {
            return SmsResult::failed($body);
        }

        return SmsResult::sent($body);
    }

    protected function request(): PendingRequest
    {
        $request = Http::timeout((int) ($this->config['timeout'] ?? 10))->acceptJson();

        $header = trim((string) ($this->config['auth_header'] ?? ''));
        if ($header !== '' && str_contains($header, ':')) {
            [$name, $value] = explode(':', $header, 2);
            $request = $request->withHeaders([trim($name) => trim($value)]);
        }

        return $request;
    }

    /**
     * Replace {to} {message} {sender} in the template, escaping each value for its context.
     */
    protected function fill(string $template, array $values, callable $escape): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{'.$key.'}', $escape($value), $template);
        }

        return $template;
    }
}
