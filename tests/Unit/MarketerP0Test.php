<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Services\MarketerAccessService;
use App\Support\PayoutRails;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketerP0Test extends TestCase
{
    public function test_mixed_case_config_networks_are_normalised(): void
    {
        $gh = PayoutRails::countries()['GH']['networks'];
        $ke = PayoutRails::countries()['KE']['networks'];

        $this->assertArrayHasKey('MTN', $gh);
        $this->assertArrayHasKey('VODAFONE', $gh);
        $this->assertArrayHasKey('AIRTELTIGO', $gh);
        $this->assertArrayHasKey('M-PESA', $ke);
        $this->assertArrayHasKey('AIRTEL MONEY', $ke);
    }

    public function test_lowercase_rail_input_is_accepted_and_canonicalised(): void
    {
        $data = PayoutRails::validate([
            'country' => 'gh',
            'network' => 'vodafone',
            'account_number' => '020 123 4567',
        ]);

        $this->assertSame('GH', $data['country']);
        $this->assertSame('VODAFONE', $data['network']);
        $this->assertSame('0201234567', $data['account_number']);
    }

    public function test_a_network_from_another_country_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        PayoutRails::validate([
            'country' => 'GH',
            'network' => 'M-PESA',
            'account_number' => '0712345678',
        ]);
    }

    public function test_an_account_that_does_not_match_the_network_pattern_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        PayoutRails::validate([
            'country' => 'GH',
            'network' => 'MTN',
            'account_number' => '0201234567',
        ]);
    }

    public function test_region_variants_match_both_tour_spellings(): void
    {
        $service = new MarketerAccessService();

        $withSuffix = new Admin(['marketer_region' => 'Greater Accra Region']);
        $withoutSuffix = new Admin(['marketer_region' => 'Greater Accra']);

        $this->assertSame(
            $service->regionsFor($withSuffix),
            $service->regionsFor($withoutSuffix)
        );
        $this->assertContains('Greater Accra', $service->regionsFor($withSuffix));
        $this->assertContains('Greater Accra Region', $service->regionsFor($withSuffix));
    }

    public function test_a_marketer_without_a_region_gets_no_territory(): void
    {
        $service = new MarketerAccessService();

        $this->assertSame([], $service->regionsFor(new Admin(['marketer_region' => null])));
    }
}
