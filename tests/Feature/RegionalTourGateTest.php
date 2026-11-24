<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\RegionalTourController;
use App\Models\RegionalTour;
use App\Models\RegionalTourRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regional tours are an account-only flow. These tests cover both doors:
 * the route middleware and the controller's own fail-closed check.
 */
class RegionalTourGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run the real migrations so the schema under test cannot drift from
        // what deployment will actually create.
        foreach ([
            '2026_11_21_000005_create_regional_tours_and_marketer_links.php',
            '2026_11_24_000002_add_user_id_to_regional_tour_registrations.php',
        ] as $file) {
            $migration = require database_path('migrations/' . $file);
            $migration->up();
        }
    }

    public function test_user_id_migration_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('regional_tour_registrations', 'user_id'));

        $migration = require database_path('migrations/2026_11_24_000002_add_user_id_to_regional_tour_registrations.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('regional_tour_registrations', 'user_id'));
    }

    public function test_tour_api_rejects_anonymous_requests(): void
    {
        $this->getJson('/api/v1/regional-tours')->assertStatus(401);

        $this->postJson('/api/v1/regional-tours/register', [
            'farmer_name' => 'Test Farmer',
            'phone' => '0201234567',
            'region' => 'Greater Accra',
        ])->assertStatus(401);

        $this->assertSame(0, RegionalTourRegistration::count());
    }

    public function test_register_fails_closed_without_a_resolved_user(): void
    {
        $request = Request::create('/api/v1/regional-tours/register', 'POST', [
            'farmer_name' => 'Test Farmer',
            'phone' => '0201234567',
            'region' => 'Greater Accra',
        ]);

        $response = (new RegionalTourController())->register($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('auth', $response->getData(true)['errors'][0]['code']);
        $this->assertSame(0, RegionalTourRegistration::count());
    }

    public function test_an_application_is_linked_to_the_signed_in_account(): void
    {
        $request = Request::create('/api/v1/regional-tours/register', 'POST', [
            'farmer_name' => 'Signed In Farmer',
            'phone' => '0201234567',
            'region' => 'Greater Accra',
        ]);
        $request->setUserResolver(fn () => (object) ['id' => 77]);

        $response = (new RegionalTourController())->register($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('regional_tour_registrations', [
            'user_id' => 77,
            'farmer_name' => 'Signed In Farmer',
            'status' => RegionalTourRegistration::STATUS_REGISTERED,
        ]);
    }

    public function test_a_user_cannot_apply_twice_to_the_same_tour(): void
    {
        $tour = RegionalTour::create([
            'title' => 'Accra farm tour',
            'region' => 'Greater Accra',
            'start_date' => '2026-12-01',
            'capacity' => 10,
            'status' => RegionalTour::STATUS_OPEN,
        ]);

        RegionalTourRegistration::create([
            'user_id' => 42,
            'tour_id' => $tour->id,
            'farmer_name' => 'First attempt',
            'phone' => '0201234567',
            'region' => 'Greater Accra',
        ]);

        $request = Request::create('/api/v1/regional-tours/register', 'POST', [
            'tour_id' => $tour->id,
            'farmer_name' => 'Second attempt',
            'phone' => '0201234567',
            'region' => 'Greater Accra',
        ]);
        $request->setUserResolver(fn () => (object) ['id' => 42]);

        $response = (new RegionalTourController())->register($request);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('duplicate', $response->getData(true)['errors'][0]['code']);
        $this->assertSame(1, RegionalTourRegistration::count());
    }
}
