<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Production caches its routes (route:cache), and the cached matcher takes the FIRST route that
 * fits an address. A Route::redirect() (which answers every method) sharing an address with a form
 * then swallows the form's POST: /login/phone once did, so "Continue" on the sign-in page did
 * nothing on iruali.mv while every test passed. No address may have a redirect and another route.
 */
class RouteCollisionTest extends TestCase
{
    public function test_no_redirect_shares_an_address_with_another_route(): void
    {
        $byUri = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $byUri[$route->getDomain().'|'.$route->uri()][] = $route;
        }

        $clashes = [];
        foreach ($byUri as $uri => $routes) {
            $redirects = array_filter($routes, fn (Route $r) => str_contains((string) $r->getActionName(), 'RedirectController'));
            if ($redirects !== [] && count($routes) > 1) {
                $clashes[] = $uri;
            }
        }

        $this->assertSame([], $clashes, 'A redirect shares its address with another route (it would catch that route\'s requests once routes are cached)');
    }

    public function test_the_sign_in_form_reaches_the_sign_in_with_the_route_cache_matcher(): void
    {
        // The same first-match lookup production uses once routes are cached
        $compiled = RouteFacade::getRoutes() instanceof RouteCollection ? RouteFacade::getRoutes()->toCompiledRouteCollection(app('router'), app()) : RouteFacade::getRoutes();
        $request = \Illuminate\Http\Request::create('/login/phone', 'POST');
        $this->assertSame('login.phone.start', $compiled->match($request)->getName());
        $this->assertSame('login.phone', $compiled->match(\Illuminate\Http\Request::create('/login/phone', 'GET'))->getName());
    }
}
