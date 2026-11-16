<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * No test reaches the real internet (an SMS gateway, Telegram, Facebook, BML, a shop's page): a
     * request nothing faked fails the test instead of going out or depending on the network.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /**
     * Turn on BML card payments (the only payment method) against a fake BML sandbox, so checkout
     * can place orders. Placing an order sends the customer to the fake payment page.
     */
    protected function enableBml(): void
    {
        config([
            'services.bml.api_key' => 'test-key',
            'services.bml.environment' => 'sandbox',
            'services.bml.base_uri' => 'https://bml.test/public',
        ]);

        Http::fake([
            'bml.test/*' => Http::response(['id' => 'txn_test', 'url' => 'https://pay.bml.test/txn_test', 'state' => 'INITIATED'], 201),
        ]);
    }
}
