<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TradeRfqController as AdminTradeRfqController;
use App\Http\Controllers\Api\V1\GroupBuyController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\RegionalTourController;
use App\Http\Controllers\Api\V1\TradeController;
use App\Models\Store;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\GroupBuy\GroupBuyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureManagementAndRfqLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->createTables();
        FeatureFlagService::clearCache();
        Cache::flush();
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
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
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
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('auth_token')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('zone_id')->default(1);
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(1);
                $table->decimal('tax', 8, 2)->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('trade_rfqs')) {
            Schema::create('trade_rfqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('buyer_name');
                $table->string('buyer_company')->nullable();
                $table->string('buyer_phone');
                $table->string('buyer_email')->nullable();
                $table->string('commodity');
                $table->string('category')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->string('unit');
                $table->decimal('target_price_ghs', 12, 2)->nullable();
                $table->string('incoterm')->default('DDP');
                $table->string('origin_country')->default('Ghana');
                $table->string('destination_country');
                $table->string('destination_city')->nullable();
                $table->string('trade_bloc')->default('ECOWAS');
                $table->boolean('requires_insurance')->default(true);
                $table->boolean('requires_phytosanitary')->default(true);
                $table->date('deadline_date')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('open');
                $table->unsignedBigInteger('accepted_quote_id')->nullable();
                $table->timestamp('awarded_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('trade_rfq_quotes')) {
            Schema::create('trade_rfq_quotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trade_rfq_id');
                $table->unsignedBigInteger('store_id')->nullable();
                $table->string('supplier_name');
                $table->string('supplier_phone');
                $table->decimal('quoted_unit_price_ghs', 12, 2);
                $table->string('incoterm')->default('DDP');
                $table->integer('lead_time_days')->default(7);
                $table->string('compliance_docs')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('submitted');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('regional_tours')) {
            Schema::create('regional_tours', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('region')->nullable();
                $table->string('district')->nullable();
                $table->string('venue')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('capacity')->default(50);
                $table->unsignedBigInteger('lead_marketer_id')->nullable();
                $table->string('status')->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('regional_tour_registrations')) {
            Schema::create('regional_tour_registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tour_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('region')->nullable();
                $table->integer('farmer_count')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->string('order_status')->default('pending');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('payment_requests')) {
            Schema::create('payment_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->decimal('payment_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function test_feature_flags_keep_domestic_wholesale_independent_of_cross_border_and_retail_zone_caps(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => FeatureFlagService::SETTING_KEY],
            ['value' => json_encode([
                'cross_border_trade' => false,
                'group_buys' => ['allow_new_transactions' => false],
                'regional_tours' => ['allow_new_transactions' => false],
            ])]
        );
        // Enable strict retail neighbourhood zone allowlist (zones 9 & 10)
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'retail_delivery_zone_ids'],
            ['value' => json_encode([9, 10])]
        );
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'max_retail_delivery_distance_km'],
            ['value' => '25']
        );
        FeatureFlagService::clearCache();
        Cache::flush();

        $service = app(FeatureFlagService::class);
        $this->assertFalse($service->allowsNewTransactions('cross_border_trade'));
        $this->assertTrue($service->allowsHistoricalAccess('cross_border_trade'));
        $this->assertTrue($service->allowsNewTransactions('domestic_wholesale'));

        // Domestic Wholesale store in Zone 1 (Ghana) stays purchasable (200 OK) even when
        // retail_delivery_zone_ids=[9,10] and cross_border_trade=false!
        $wholesaleStore = Store::create([
            'name' => 'Techiman Maize Wholesale',
            'zone_id' => 1,
            'module_id' => 2,
            'status' => 1,
            'tax' => 0,
        ]);
        $retailZone1Store = Store::create([
            'name' => 'Misplaced Retail Store in Zone 1',
            'zone_id' => 1,
            'module_id' => 1,
            'status' => 1,
            'tax' => 0,
        ]);

        $orderController = new OrderController();
        $wholesaleSummary = $orderController->checkout_summary(Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'delivery',
            'order_amount' => 2500,
            'distance' => 120,
            'store_id' => $wholesaleStore->id,
        ]));
        $this->assertSame(200, $wholesaleSummary->getStatusCode());

        // Retail store in Zone 1 IS blocked by retail_delivery_zone_ids (403)
        $retailSummary = $orderController->checkout_summary(Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'delivery',
            'order_amount' => 85,
            'distance' => 8,
            'store_id' => $retailZone1Store->id,
        ]));
        $this->assertSame(403, $retailSummary->getStatusCode());

        // GroupBuy creation & join blocked (403)
        $gbController = new GroupBuyController(app(GroupBuyService::class));
        $this->assertSame(403, $gbController->store(Request::create('/api/v1/group-buys', 'POST'))->getStatusCode());
        $this->assertSame(403, $gbController->join(Request::create('/api/v1/group-buys/1/join', 'POST'), 1)->getStatusCode());

        // RegionalTour registration blocked (403), while historical/public index stays 200
        $rtController = new RegionalTourController();
        $this->assertSame(200, $rtController->index(Request::create('/api/v1/regional-tours', 'GET'))->getStatusCode());
        $this->assertSame(403, $rtController->register(Request::create('/api/v1/regional-tours/register', 'POST'))->getStatusCode());
    }

    public function test_rfq_commitments_blocked_by_default_across_public_and_admin_while_preserving_redacted_historical_access(): void
    {
        DB::table('trade_rfqs')->insert([
            'id' => 10,
            'user_id' => 5,
            'buyer_name' => 'Secret Buyer Name',
            'buyer_company' => 'Tema Agro Processing',
            'buyer_phone' => '0244999888',
            'buyer_email' => 'secret.buyer@example.com',
            'commodity' => 'White Maize',
            'quantity' => 50,
            'unit' => 'bags',
            'destination_country' => 'Ghana',
            'status' => 'quoted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('trade_rfq_quotes')->insert([
            'id' => 77,
            'trade_rfq_id' => 10,
            'store_id' => 201,
            'supplier_name' => 'Ejura Farms',
            'supplier_phone' => '0201112222',
            'quoted_unit_price_ghs' => 420.00,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new TradeController();

        // 1. Unauthenticated historical read succeeds (200) WITHOUT leaking buyer PII or supplier quote prices/phones
        $publicListRes = $controller->requests(Request::create('/api/v1/trade/rfqs', 'GET'));
        $this->assertSame(200, $publicListRes->getStatusCode());
        $publicData = $publicListRes->getData(true);
        $this->assertSame(1, $publicData['total']);
        $first = $publicData['requests'][0];
        $this->assertSame('Tema Agro Processing', $first['buyer_company']);
        $this->assertSame(1, $first['quotes_count']);
        $this->assertSame([], $first['quotes']);
        $this->assertArrayNotHasKey('buyer_phone', $first);
        $this->assertArrayNotHasKey('buyer_email', $first);
        $this->assertArrayNotHasKey('buyer_name', $first);

        // 2. Owning buyer CAN view their historical RFQ & received quotes (with unaccepted supplier phone redacted)
        $ownerReq = Request::create('/api/v1/trade/rfqs', 'GET');
        $ownerReq->setUserResolver(fn () => new User(['id' => 5]));
        $ownerData = $controller->requests($ownerReq)->getData(true)['requests'][0];
        $this->assertSame('0244999888', $ownerData['buyer_phone']);
        $this->assertCount(1, $ownerData['quotes']);
        $this->assertSame(420.0, (float) $ownerData['quotes'][0]['quoted_unit_price_ghs']);
        $this->assertNull($ownerData['quotes'][0]['supplier_phone']);

        // 3. Public API create/quote/accept AND Admin acceptQuote are all blocked (403) while rfq_new_commitments is false
        $this->assertSame(403, $controller->store_rfq(Request::create('/api/v1/trade/rfqs', 'POST'))->getStatusCode());
        $this->assertSame(403, $controller->store_quote(Request::create('/api/v1/trade/rfqs/10/quotes', 'POST'), 10)->getStatusCode());
        $this->assertSame(403, $controller->accept_quote(Request::create('/api/v1/trade/rfqs/10/quotes/77/accept', 'POST'), 10, 77)->getStatusCode());

        $adminController = new AdminTradeRfqController();
        $adminJsonReq = Request::create('/admin/trade-rfqs/quotes/77/accept', 'POST');
        $adminJsonReq->headers->set('Accept', 'application/json');
        $adminRes = $adminController->acceptQuote($adminJsonReq, 77);
        $this->assertSame(403, $adminRes->getStatusCode());
        $this->assertSame('quoted', DB::table('trade_rfqs')->where('id', 10)->value('status'));
    }

    public function test_rfq_lifecycle_when_enabled_enforces_buyer_and_supplier_permissions_and_quote_isolation(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => FeatureFlagService::SETTING_KEY],
            ['value' => json_encode([
                'rfq_new_commitments' => ['allow_new_transactions' => true],
            ])]
        );
        FeatureFlagService::clearCache();

        // Seed two approved supplier vendors/stores and one unapproved vendor/store
        DB::table('vendors')->insert([
            ['id' => 1, 'f_name' => 'Supplier A', 'auth_token' => 'tok-supplier-a', 'status' => 1],
            ['id' => 2, 'f_name' => 'Supplier B', 'auth_token' => 'tok-supplier-b', 'status' => 1],
            ['id' => 3, 'f_name' => 'Unapproved Supplier', 'auth_token' => 'tok-unapproved', 'status' => 1],
        ]);
        Store::create(['id' => 301, 'name' => 'Techiman Coop', 'vendor_id' => 1, 'status' => 1]);
        Store::create(['id' => 302, 'name' => 'Tamale Grains', 'vendor_id' => 2, 'status' => 1]);
        Store::create(['id' => 303, 'name' => 'Pending Store', 'vendor_id' => 3, 'status' => 0]);

        $buyer = new User(['id' => 42]);
        $otherUser = new User(['id' => 99]);
        $controller = new TradeController();

        // 1. Unauthenticated store_rfq rejected (401)
        $this->assertSame(401, $controller->store_rfq(Request::create('/api/v1/trade/rfqs', 'POST'))->getStatusCode());

        // 2. Authenticated buyer creates RFQ (201)
        $createReq = Request::create('/api/v1/trade/rfqs', 'POST', [
            'buyer_name' => 'Accra Bulk Foods',
            'buyer_phone' => '0244111222',
            'commodity' => 'Yellow Maize',
            'quantity' => 20,
            'unit' => 'tonnes',
            'destination_country' => 'Ghana',
            'incoterm' => 'DDP',
        ]);
        $createReq->setUserResolver(fn () => $buyer);
        $createRes = $controller->store_rfq($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $rfqId = $createRes->getData(true)['rfq']['id'];

        // 3. Unauthenticated or unapproved-store supplier cannot quote (403); spoofing rival store_id rejected (403)
        $this->assertSame(403, $controller->store_quote(Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST'), $rfqId)->getStatusCode());

        $unapprovedQuoteReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Pending Store',
            'supplier_phone' => '0244000999',
            'quoted_unit_price_ghs' => 4000,
        ]);
        $unapprovedQuoteReq->headers->set('Authorization', 'Bearer tok-unapproved');
        $this->assertSame(403, $controller->store_quote($unapprovedQuoteReq, $rfqId)->getStatusCode());

        $spoofStoreReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Techiman Coop',
            'supplier_phone' => '0244333444',
            'store_id' => 302, // owned by Supplier B!
            'quoted_unit_price_ghs' => 4500,
        ]);
        $spoofStoreReq->headers->set('Authorization', 'Bearer tok-supplier-a');
        $this->assertSame(403, $controller->store_quote($spoofStoreReq, $rfqId)->getStatusCode());

        // 4. Supplier A and Supplier B submit legitimate quotes
        $q1Req = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Techiman Coop',
            'supplier_phone' => '0244333444',
            'quoted_unit_price_ghs' => 4500,
        ]);
        $q1Req->headers->set('Authorization', 'Bearer tok-supplier-a');
        $q1Id = $controller->store_quote($q1Req, $rfqId)->getData(true)['quote']['id'];

        $q2Req = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Tamale Grains',
            'supplier_phone' => '0244555666',
            'quoted_unit_price_ghs' => 4800,
        ]);
        $q2Req->headers->set('Authorization', 'Bearer tok-supplier-b');
        $q2Id = $controller->store_quote($q2Req, $rfqId)->getData(true)['quote']['id'];

        // 5. Supplier A querying RFQs sees ONLY their own quote (store 301), never Supplier B's price!
        $supplierListReq = Request::create('/api/v1/trade/rfqs', 'GET');
        $supplierListReq->headers->set('Authorization', 'Bearer tok-supplier-a');
        $supplierView = $controller->requests($supplierListReq)->getData(true)['requests'][0];
        $this->assertSame(2, $supplierView['quotes_count']);
        $this->assertCount(1, $supplierView['quotes']);
        $this->assertSame(301, (int) $supplierView['quotes'][0]['store_id']);

        // 6. Non-owner user cannot accept buyer's RFQ quote (403)
        $wrongUserReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes/{$q1Id}/accept", 'POST');
        $wrongUserReq->setUserResolver(fn () => $otherUser);
        $this->assertSame(403, $controller->accept_quote($wrongUserReq, $rfqId, $q1Id)->getStatusCode());

        // 7. Buyer accepts winning quote -> awarded, competing quote declined, 0 orders/payment_requests created
        $acceptReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes/{$q1Id}/accept", 'POST');
        $acceptReq->setUserResolver(fn () => $buyer);
        $acceptRes = $controller->accept_quote($acceptReq, $rfqId, $q1Id);
        $this->assertSame(200, $acceptRes->getStatusCode());
        $this->assertSame('awarded', DB::table('trade_rfqs')->where('id', $rfqId)->value('status'));
        $this->assertSame('accepted', DB::table('trade_rfq_quotes')->where('id', $q1Id)->value('status'));
        $this->assertSame('declined', DB::table('trade_rfq_quotes')->where('id', $q2Id)->value('status'));
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('payment_requests')->count());
    }
}
