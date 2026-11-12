<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use App\Models\TradeCorridor;
use App\Models\CustomsDuty;
use App\Models\TradeBloc;
use Tests\CreatesApplication;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use CreatesApplication;

    /**
     * Test that the market index page loads with commodities.
     */
    public function test_market_index_loads()
    {
        $category = Category::create([
            'name' => 'Test Cereals',
            'module_id' => 20,
            'position' => 0,
            'status' => 1,
            'image' => 'def.png',
            'featured' => 0,
            'products_count' => 0,
            'childes_count' => 0,
        ]);

        $user = User::create([
            'f_name' => 'Test',
            'l_name' => 'Vendor',
            'email' => 'vendor' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        $store = Store::create([
            'name' => 'Test Store',
            'email' => 'store' . uniqid() . '@test.com',
            'phone' => '+233242371341',
            'status' => 1,
            'vendor_id' => $user->id,
            'module_id' => 20,
        ]);

        Item::create([
            'name' => 'Test Maize',
            'slug' => 'test-maize-' . uniqid(),
            'description' => 'Test maize commodity',
            'price' => 2.50,
            'unit' => 'kg',
            'module_id' => 20,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'status' => 1,
            'origin_farm' => 'Ghana',
            'hs_code' => '100590',
            'minimum_order_qty' => 100,
        ]);

        $response = $this->get('/market');

        $response->assertStatus(200);
        $response->assertSee('Test Maize');
    }

    /**
     * Test that trade corridors exist.
     */
    public function test_trade_corridors_exist()
    {
        TradeCorridor::create([
            'origin_country' => 'Ghana',
            'destination_country' => 'Nigeria',
            'corridor_name' => 'Ghana → Nigeria',
            'distance_km' => 650,
            'transit_days_estimate' => 5,
            'trucking_rate_per_ton_usd' => 180,
            'minimum_charge_usd' => 400,
            'cold_chain_available' => 1,
            'warehousing_available' => 1,
            'status' => 'active',
        ]);

        $corridor = TradeCorridor::between('Ghana', 'Nigeria')->first();

        $this->assertNotNull($corridor);
        $this->assertEquals('Ghana', $corridor->origin_country);
        $this->assertEquals('Nigeria', $corridor->destination_country);
        $this->assertGreaterThan(0, $corridor->distance_km);
    }

    /**
     * Test that customs duties are calculated correctly.
     */
    public function test_customs_duty_calculation()
    {
        CustomsDuty::create([
            'destination_country' => 'Nigeria',
            'hs_code' => '10',
            'hs_description' => 'Cereals',
            'duty_rate_percent' => 5.0,
            'vat_rate_percent' => 7.5,
            'excise_rate_percent' => 0,
            'requires_sps_certificate' => 1,
            'requires_phytosanitary' => 1,
            'requires_origin_certificate' => 1,
        ]);

        $duty = CustomsDuty::calculateDuties(1000, 'Nigeria', '100590');

        $this->assertEquals(50.0, $duty['duty_usd']);
        $this->assertEquals(78.75, $duty['vat_usd']);
        $this->assertEquals(128.75, $duty['total_usd']);
        $this->assertContains('SPS Certificate', $duty['documents_required']);
    }

    /**
     * Test that trade blocs exist.
     */
    public function test_trade_blocs_exist()
    {
        TradeBloc::create([
            'name' => 'Economic Community of West African States (ECOWAS)',
            'slug' => 'ecowas',
            'description' => 'West African trade bloc.',
            'member_countries' => json_encode(['Nigeria', 'Ghana', 'Benin']),
            'primary_currencies' => json_encode(['GHS', 'NGN']),
            'common_languages' => json_encode(['English', 'French']),
            'status' => 'active',
        ]);

        $ecowas = TradeBloc::where('slug', 'ecowas')->first();

        $this->assertNotNull($ecowas);
        $this->assertContains('Nigeria', $ecowas->member_countries);
        $this->assertContains('Ghana', $ecowas->member_countries);
    }

    /**
     * Test that the trade bloc hub page loads.
     */
    public function test_trade_bloc_hub_loads()
    {
        $bloc = TradeBloc::create([
            'name' => 'Test Bloc',
            'slug' => 'testbloc',
            'description' => 'Test description',
            'member_countries' => json_encode(['Ghana', 'Nigeria']),
            'primary_currencies' => json_encode(['GHS', 'NGN']),
            'common_languages' => json_encode(['English']),
            'status' => 'active',
        ]);

        TradeCorridor::create([
            'origin_country' => 'Ghana',
            'destination_country' => 'Nigeria',
            'corridor_name' => 'Ghana → Nigeria',
            'distance_km' => 650,
            'transit_days_estimate' => 5,
            'trucking_rate_per_ton_usd' => 180,
            'minimum_charge_usd' => 400,
            'cold_chain_available' => 1,
            'warehousing_available' => 1,
            'status' => 'active',
        ]);

        $response = $this->get('/trade-bloc/testbloc');

        $response->assertStatus(200);
        $response->assertSee('Test Bloc');
    }
}
