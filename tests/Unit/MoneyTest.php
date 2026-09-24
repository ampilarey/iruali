<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_rufiyaa_as_mvr_in_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('MVR 1,250.00', Money::format(1250));
        $this->assertSame('MVR 0.00', Money::format(null));
    }

    public function test_uses_rufiyaa_sign_in_dhivehi(): void
    {
        app()->setLocale('dv');

        $this->assertSame("ރ.\u{200E} 99.50", Money::format('99.5'));
    }
}
