<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('zones')) {
            Schema::create('zones', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('module_name')->nullable();
                $table->string('module_type')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
            \Illuminate\Support\Facades\DB::table('modules')->insert([
                'id' => 1,
                'module_name' => 'Grocery',
                'module_type' => 'grocery',
                'status' => true,
            ]);
        }
        if (! Schema::hasTable('storages')) {
            Schema::create('storages', function (Blueprint $table) {
                $table->id();
                $table->string('data_type')->nullable();
                $table->unsignedBigInteger('data_id')->nullable();
                $table->string('key')->nullable();
                $table->string('value')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('translations')) {
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
    }

    public function test_route_names_have_no_duplicates_and_no_closures(): void
    {
        $routes = app('router')->getRoutes();
        $names = [];
        foreach ($routes as $route) {
            if ($name = $route->getName()) {
                $this->assertArrayNotHasKey($name, $names, "Duplicate route name detected: {$name}");
                $names[$name] = true;
            }
            $this->assertNotSame('Closure', $route->getActionName(), 'Closure route blocks route:cache: ' . $route->uri());
        }
    }

    public function test_for_you_items_requires_zone_header(): void
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\DefaultBrowseContextMiddleware::class)
            ->withHeaders(['moduleId' => '1'])
            ->getJson('/api/v1/items/for-you');
        $response->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'zoneId');
    }

    public function test_verify_payout_validates_short_account_number(): void
    {
        $response = $this->postJson('/api/v1/marketer-applications/verify-payout', [
            'account_number' => '123',
        ]);
        $response->assertStatus(422);
    }

    public function test_pro_customer_faqs_and_terms_endpoints_return_200(): void
    {
        $this->withHeaders(['moduleId' => '1'])->getJson('/api/v1/pro-customer/faqs')
            ->assertStatus(200)
            ->assertJsonStructure(['faqs']);

        $this->withHeaders(['moduleId' => '1'])->getJson('/api/v1/pro-customer/terms-and-conditions')
            ->assertStatus(200)
            ->assertJsonStructure(['terms']);
    }

    public function test_shared_login_pages_render_recaptcha_when_enabled(): void
    {
        // Admin/store controllers call passesHumanCheck() but used to have no
        // token field on the page, so reCAPTCHA-enabled deployments could not
        // sign in. Every renderer of auth.login must now supply the fields.
        \Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(
            ['key' => 'recaptcha'],
            ['value' => json_encode([
                'status' => 1,
                'site_key' => 'test-site-key',
                'secret_key' => 'test-secret-key',
            ])]
        );
        \Illuminate\Support\Facades\Cache::forget('business_settings_all_data');

        foreach (['/admin-login', '/store-login', '/marketer-login'] as $url) {
            $this->get($url)->assertStatus(200)
                ->assertSee('name="g-recaptcha-response"', false)
                ->assertSee('test-site-key', false);
        }
    }

    public function test_portal_login_pages_render(): void
    {
        $this->get('/admin-login')->assertStatus(200)
            ->assertSee('id="loginForm"', false);

        // Marketers use the same auth.login component as admin/store. The old
        // bespoke page had its own form id, so assert both the shared markers
        // and the absence of the old ones.
        $this->get('/marketer-login')->assertStatus(200)
            ->assertSee('id="loginForm"', false)
            ->assertSee('Sign in to your marketer panel', false)
            ->assertSee('marketer-forgot-password', false)
            ->assertSee('name="custome_recaptcha"', false)
            ->assertDontSee('marketer-login-form', false);

        $this->get('/rider-login')->assertStatus(200);
    }
}
