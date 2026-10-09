<?php

namespace Tests\Unit;

use App\Rules\MiraTin;
use App\Services\GstService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GstMathsTest extends TestCase
{
    public function test_gst_is_the_tax_fraction_of_a_gst_inclusive_amount_rounded_to_the_laari(): void
    {
        $this->assertSame(8.0, GstService::gstIncluded(108, 8));
        $this->assertSame(80.0, GstService::gstIncluded('1080.00', '8.00'));
        $this->assertSame(7.41, GstService::gstIncluded(100, 8));      // 7.4074…
        $this->assertSame(74.07, GstService::gstIncluded(1000, 8));    // 74.0740…
        $this->assertSame(3.74, GstService::gstIncluded(50.5, 8));     // 3.7407…
        $this->assertSame(0.74, GstService::gstIncluded(10, 8));       // 0.7407…
        $this->assertSame(6.0, GstService::gstIncluded(81, 8));
        $this->assertSame(16.0, GstService::gstIncluded(116, 16));
        $this->assertSame(-7.41, GstService::gstIncluded(-100, 8));
    }

    public function test_small_amounts_and_exact_halves_round_half_up(): void
    {
        $this->assertSame(0.01, GstService::gstIncluded('0.13', 8));   // 0.0096 → 0.01
        $this->assertSame(0.0, GstService::gstIncluded(0.06, 8));      // 0.0044 → 0.00
        // At 100% the GST is half the amount, so odd laari amounts land exactly on half a laari
        $this->assertSame(0.01, GstService::gstIncluded(0.01, 100));   // 0.005 → 0.01
        $this->assertSame(1.01, GstService::gstIncluded(2.01, 100));   // 1.005 → 1.01 (floats would say 1.00499…)
        $this->assertSame(0.02, GstService::gstIncluded(0.03, 100));   // 0.015 → 0.02
    }

    public function test_no_rate_or_no_amount_means_no_gst(): void
    {
        $this->assertSame(0.0, GstService::gstIncluded(100, 0));
        $this->assertSame(0.0, GstService::gstIncluded(100, null));
        $this->assertSame(0.0, GstService::gstIncluded(null, 8));
        $this->assertSame(0.0, GstService::gstIncluded(0, 8));
    }

    public function test_tins_are_normalised_and_checked_against_the_mira_format(): void
    {
        $this->assertSame('1012345GST501', GstService::normaliseTin(' 1012345 gst 501 '));
        $this->assertSame('1012345GST501', GstService::normaliseTin('1012345gst501'));
        $this->assertSame('', GstService::normaliseTin(['1012345GST501']));
        $this->assertSame('', GstService::normaliseTin(null));

        $this->assertTrue(GstService::isValidTin('1012345GST501'));
        $this->assertFalse(GstService::isValidTin('101234GST501'));    // 6 digits
        $this->assertFalse(GstService::isValidTin('1012345GST50'));    // 2 digits at the end
        $this->assertFalse(GstService::isValidTin('1012345-GST-501'));
        $this->assertFalse(GstService::isValidTin('1012345TIN501'));
        $this->assertFalse(GstService::isValidTin(''));
        $this->assertFalse(GstService::isValidTin(null));

        $this->assertTrue(Validator::make(['tin' => '1012345 gst 501'], ['tin' => [new MiraTin]])->passes());
        $this->assertTrue(Validator::make(['tin' => '1012345GST50'], ['tin' => [new MiraTin]])->fails());
    }
}
