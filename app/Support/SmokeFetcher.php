<?php

namespace App\Support;

use Illuminate\Http\Client\Response;

/**
 * How SmokeChecks talks to the site. The real one (HttpSmokeFetcher) keeps a cookie jar so the
 * sign-in and the cart survive between requests; tests swap in a fake that answers from a map.
 */
interface SmokeFetcher
{
    /**
     * @param  array<string, mixed>  $data  form fields for a POST
     */
    public function request(string $method, string $url, array $data = []): Response;
}
