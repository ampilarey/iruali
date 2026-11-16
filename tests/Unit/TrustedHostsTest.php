<?php

namespace Tests\Unit;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Only APP_URL's host and its subdomains are answered (bootstrap/app.php trustHosts()).
 */
class TrustedHostsTest extends TestCase
{
    protected function check(string $host): bool
    {
        config(['app.url' => 'https://iruali.mv']);
        $middleware = new class($this->app) extends TrustHosts
        {
            protected function shouldSpecifyTrustedHosts()
            {
                return true; // as on the live site (off in tests and locally)
            }
        };
        $request = Request::create('https://'.$host.'/');
        try {
            $middleware->handle($request, fn ($r) => $r->getHost());

            return true;
        } catch (\Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException) {
            return false;
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    public function test_the_sites_own_hosts_are_answered_and_others_refused(): void
    {
        $this->assertTrue($this->check('iruali.mv'));
        $this->assertTrue($this->check('www.iruali.mv'));
        $this->assertTrue($this->check('test.iruali.mv'));
        $this->assertFalse($this->check('evil.example'));
        $this->assertFalse($this->check('iruali.mv.evil.example'));
    }

    public function test_it_is_registered_for_every_request(): void
    {
        $this->assertTrue(in_array(TrustHosts::class, $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->getGlobalMiddleware(), true));
    }
}
