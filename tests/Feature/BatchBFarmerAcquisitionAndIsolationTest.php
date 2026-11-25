<?php

namespace Tests\Feature;

use App\Http\Controllers\MarketerPortalController;
use App\Models\Admin;
use App\Models\BackyardFarmApplication;
use App\Models\Module;
use App\Models\RegionalTour;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\MarketerAccessService;
use App\Services\MarketerCommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BatchBFarmerAcquisitionAndIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->createTables();
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('translationable_type')->nullable();
                $table->unsignedBigInteger('translationable_id')->nullable();
                $table->string('locale')->nullable();
                $table->string('key')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('storages')) {
            Schema::create('storages', function (Blueprint $table) {
                $table->id();
                $table->string('data_type')->nullable();
                $table->unsignedBigInteger('data_id')->nullable();
                $table->string('key')->nullable();
                $table->string('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('zones')) {
            Schema::create('zones', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('module_name')->nullable();
                $table->string('module_type')->default('grocery');
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->string('marketer_code')->nullable();
                $table->string('marketer_region')->nullable();
                $table->decimal('marketer_commission_rate', 8, 3)->nullable();
                $table->decimal('onboarding_bounty_ghs', 10, 2)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('phone')->unique();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->integer('status')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(0);
                $table->decimal('tax', 8, 2)->default(0);
                $table->string('delivery_time')->nullable();
                $table->string('store_business_model')->default('commission');
                $table->string('farm_type')->nullable();
                $table->boolean('is_backyard')->default(false);
                $table->string('region')->nullable();
                $table->string('district')->nullable();
                $table->string('cultivation_status')->default('active');
                $table->unsignedBigInteger('marketer_id')->nullable();
                $table->string('marketer_code')->nullable();
                $table->timestamp('marketer_assigned_at')->nullable();
                $table->boolean('marketer_verified')->default(false);
                $table->timestamp('marketer_verified_at')->nullable();
                $table->text('marketer_verification_notes')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('backyard_farm_applications')) {
            Schema::create('backyard_farm_applications', function (Blueprint $table) {
                $table->id();
                $table->string('farm_name')->nullable();
                $table->string('zone_id')->nullable();
                $table->unsignedBigInteger('marketer_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('regional_tours')) {
            Schema::create('regional_tours', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('region')->nullable();
                $table->unsignedBigInteger('lead_marketer_id')->nullable();
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('marketer_audit_logs')) {
            Schema::create('marketer_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->string('action')->nullable();
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->text('before')->nullable();
                $table->text('after')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_marketer_assisted_farmer_registration_stamps_attribution_and_pending_verification(): void
    {
        Zone::create(['id' => 4, 'name' => 'ASHANTI REGION', 'status' => 1]);
        Module::create(['id' => 2, 'module_name' => 'Wholesale & Cross-Border Trade', 'module_type' => 'grocery', 'status' => 1]);

        $marketer = Admin::create([
            'f_name' => 'Kwame',
            'l_name' => 'Mensah',
            'email' => 'kwame@aduanefie.com',
            'role_id' => 5,
            'marketer_code' => 'MKT-ASH-001',
            'marketer_region' => 'Ashanti Region',
        ]);

        Auth::guard('admin')->setUser($marketer);

        $request = Request::create('/marketer/register-farmer', 'POST', [
            'farm_name' => 'Ejura Maize Cooperative',
            'farm_type' => 'cooperative',
            'owner_f_name' => 'Kofi',
            'owner_l_name' => 'Adjei',
            'phone' => '0244112233',
            'email' => 'ejura.coop@example.com',
            'region' => 'Ashanti Region',
            'district' => 'Ejura Sekyedumase',
            'zone_id' => 4,
            'module_id' => 2,
            'address' => 'Ejura Market Road',
            'primary_crops' => 'White Maize, Yellow Maize',
        ]);

        $controller = new MarketerPortalController(
            new MarketerCommissionService(),
            new MarketerAccessService()
        );

        $response = $controller->storeRegisterFarmer($request);
        $this->assertSame(302, $response->getStatusCode());

        $vendor = Vendor::where('email', 'ejura.coop@example.com')->first();
        $this->assertNotNull($vendor);
        $this->assertSame(0, (int) $vendor->status);

        $store = Store::where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($store);
        $this->assertSame(0, (int) $store->status, 'Newly registered farm must await Admin approval (status = 0).');
        $this->assertSame('commission', $store->store_business_model);
        $this->assertSame($marketer->id, (int) $store->marketer_id);
        $this->assertSame('MKT-ASH-001', $store->marketer_code);
        $this->assertNotNull($store->marketer_assigned_at, 'marketer_assigned_at must be stamped for forward commission accrual.');
    }

    public function test_marketer_nav_badges_and_queries_are_strictly_isolated_by_marketer_territory(): void
    {
        $ashantiMarketer = Admin::create([
            'f_name' => 'Ashanti',
            'l_name' => 'Lead',
            'email' => 'ashanti@aduanefie.com',
            'role_id' => 5,
            'zone_id' => 4,
            'marketer_region' => 'Ashanti Region',
        ]);

        $voltaMarketer = Admin::create([
            'f_name' => 'Volta',
            'l_name' => 'Lead',
            'email' => 'volta@aduanefie.com',
            'role_id' => 5,
            'zone_id' => 3,
            'marketer_region' => 'Volta Region',
        ]);

        // 2 pending backyard farms in Ashanti (zone 4), 1 in Volta (zone 3)
        BackyardFarmApplication::create(['farm_name' => 'Kumasi Backyard 1', 'zone_id' => '4', 'status' => 'pending']);
        BackyardFarmApplication::create(['farm_name' => 'Kumasi Backyard 2', 'zone_id' => '4', 'status' => 'pending']);
        BackyardFarmApplication::create(['farm_name' => 'Ho Backyard 1', 'zone_id' => '3', 'status' => 'pending']);

        // 1 open tour in Ashanti, 2 open tours in Volta
        RegionalTour::create(['title' => 'Ejura Tour', 'region' => 'Ashanti', 'status' => 'open']);
        RegionalTour::create(['title' => 'Ho Tour 1', 'region' => 'Volta', 'status' => 'open']);
        RegionalTour::create(['title' => 'Keta Tour 2', 'region' => 'Volta Region', 'status' => 'upcoming']);

        // Render _nav.blade.php for Ashanti marketer
        $ashantiNavHtml = view('marketer._nav', [
            'active' => 'dashboard',
            'marketer' => $ashantiMarketer,
        ])->render();

        // Render _nav.blade.php for Volta marketer
        $voltaNavHtml = view('marketer._nav', [
            'active' => 'dashboard',
            'marketer' => $voltaMarketer,
        ])->render();

        // Ashanti marketer sees 2 backyard farms and 1 tour (not 3 and 3)
        $this->assertStringContainsString('>2</span>', $ashantiNavHtml);
        $this->assertStringContainsString('>1</span>', $ashantiNavHtml);
        $this->assertStringNotContainsString('>3</span>', $ashantiNavHtml);

        // Volta marketer sees 1 backyard farm and 2 tours (not 3 and 3)
        $this->assertStringContainsString('>1</span>', $voltaNavHtml);
        $this->assertStringContainsString('>2</span>', $voltaNavHtml);
        $this->assertStringNotContainsString('>3</span>', $voltaNavHtml);
    }
}
