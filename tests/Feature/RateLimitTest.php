<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each rate-limited route counts on its own: a plain `throttle:5,1` shares one counter per visitor
 * across every throttled route, so typing in the search box (search suggestions) used up the five
 * sign-in attempts a minute and the sign-in page answered "Too many requests".
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_searching_does_not_use_up_the_sign_in_attempts(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->getJson('/search/suggest?q=rope')->assertOk();
        }

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertStatus(302);
    }

    public function test_sign_in_is_still_limited_to_five_attempts_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_every_numeric_throttle_names_its_own_counter(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && preg_match('/^throttle:\d+,\d+$/', $middleware)) {
                    $this->fail($route->uri().' uses '.$middleware.' without a name: add one (throttle:N,M,name)');
                }
            }
        }
        $this->addToAssertionCount(1);
    }
}
