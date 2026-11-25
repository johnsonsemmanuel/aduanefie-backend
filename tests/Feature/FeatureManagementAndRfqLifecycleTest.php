<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\TradeController;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
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
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
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

    public function test_feature_flags_keep_domestic_wholesale_independent_of_cross_border_trade(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => FeatureFlagService::SETTING_KEY],
            ['value' => json_encode([
                'cross_border_trade' => false,
            ])]
        );
        FeatureFlagService::clearCache();

        $service = app(FeatureFlagService::class);

        // Cross-border trade disabled for new transactions & public visibility,
        // while historical access remains preserved.
        $this->assertFalse($service->isPubliclyVisible('cross_border_trade'));
        $this->assertFalse($service->allowsNewTransactions('cross_border_trade'));
        $this->assertTrue($service->allowsHistoricalAccess('cross_border_trade'));

        // Domestic wholesale remains completely unaffected and enabled.
        $this->assertTrue($service->isPubliclyVisible('domestic_wholesale'));
        $this->assertTrue($service->allowsNewTransactions('domestic_wholesale'));
    }

    public function test_rfq_new_commitments_are_blocked_by_default_while_historical_reads_remain_accessible(): void
    {
        DB::table('trade_rfqs')->insert([
            'id' => 10,
            'user_id' => 5,
            'buyer_name' => 'Historical Buyer',
            'buyer_phone' => '0244000000',
            'commodity' => 'White Maize',
            'quantity' => 50,
            'unit' => 'bags',
            'destination_country' => 'Ghana',
            'status' => 'awarded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new TradeController();

        // Historical RFQ list remains accessible (200 OK)
        $listRes = $controller->requests(Request::create('/api/v1/trade/rfqs', 'GET'));
        $this->assertSame(200, $listRes->getStatusCode());
        $this->assertSame(1, $listRes->getData(true)['total']);

        // New RFQ creation, quote submission, and quote acceptance are blocked by default (403)
        $createRes = $controller->store_rfq(Request::create('/api/v1/trade/rfqs', 'POST', [
            'buyer_name' => 'New Buyer',
            'buyer_phone' => '0244111111',
            'commodity' => 'Shea Nuts',
            'quantity' => 10,
            'unit' => 'tonnes',
            'destination_country' => 'Nigeria',
        ]));
        $this->assertSame(403, $createRes->getStatusCode());
        $this->assertSame('rfq_commitments_disabled', $createRes->getData(true)['code']);

        $quoteRes = $controller->store_quote(Request::create('/api/v1/trade/rfqs/10/quotes', 'POST', [
            'supplier_name' => 'Farm A',
            'supplier_phone' => '0244222222',
            'quoted_unit_price_ghs' => 100,
        ]), 10);
        $this->assertSame(403, $quoteRes->getStatusCode());

        $acceptRes = $controller->accept_quote(Request::create('/api/v1/trade/rfqs/10/quotes/1/accept', 'POST'), 10, 1);
        $this->assertSame(403, $acceptRes->getStatusCode());
    }

    public function test_rfq_lifecycle_when_enabled_enforces_owner_auth_and_documents_no_order_or_escrow_creation(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => FeatureFlagService::SETTING_KEY],
            ['value' => json_encode([
                'rfq_new_commitments' => ['allow_new_transactions' => true],
            ])]
        );
        FeatureFlagService::clearCache();

        $buyer = new User(['id' => 42]);
        $otherUser = new User(['id' => 99]);
        $controller = new TradeController();

        // 1. Create RFQ as authenticated buyer
        $createReq = Request::create('/api/v1/trade/rfqs', 'POST', [
            'buyer_name' => 'Accra Bulk Foods',
            'buyer_phone' => '0244111222',
            'commodity' => 'Yellow Maize',
            'quantity' => 20,
            'unit' => 'tonnes',
            'destination_country' => 'Ghana',
            'incoterm' => 'DDP',
        ]);
        $this->actingAs($buyer, 'api');
        $createRes = $controller->store_rfq($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $rfqId = $createRes->getData(true)['rfq']['id'];

        // 2. Two suppliers submit quotes -> RFQ transitions from open to quoted
        $q1Res = $controller->store_quote(Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Techiman Coop',
            'supplier_phone' => '0244333444',
            'quoted_unit_price_ghs' => 4500,
        ]), $rfqId);
        $this->assertSame(201, $q1Res->getStatusCode());
        $q1Id = $q1Res->getData(true)['quote']['id'];

        $q2Res = $controller->store_quote(Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes", 'POST', [
            'supplier_name' => 'Tamale Grains',
            'supplier_phone' => '0244555666',
            'quoted_unit_price_ghs' => 4800,
        ]), $rfqId);
        $this->assertSame(201, $q2Res->getStatusCode());
        $q2Id = $q2Res->getData(true)['quote']['id'];
        $this->assertSame('quoted', DB::table('trade_rfqs')->where('id', $rfqId)->value('status'));

        // 3. Another user cannot accept buyer's RFQ quote (403)
        $wrongUserReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes/{$q1Id}/accept", 'POST');
        $wrongUserReq->setUserResolver(fn () => $otherUser);
        $this->actingAs($otherUser, 'api');
        $this->assertSame(403, $controller->accept_quote($wrongUserReq, $rfqId, $q1Id)->getStatusCode());

        // 4. Owner accepts winning quote -> awarded, competing quote declined
        $acceptReq = Request::create("/api/v1/trade/rfqs/{$rfqId}/quotes/{$q1Id}/accept", 'POST');
        $acceptReq->setUserResolver(fn () => $buyer);
        $this->actingAs($buyer, 'api');
        $acceptRes = $controller->accept_quote($acceptReq, $rfqId, $q1Id);
        $this->assertSame(200, $acceptRes->getStatusCode());
        $body = $acceptRes->getData(true);
        $this->assertSame(90000.0, (float) $body['total_contract_ghs']);
        $this->assertSame(27000.0, (float) $body['deposit_due_ghs']);
        $this->assertSame('awarded', DB::table('trade_rfqs')->where('id', $rfqId)->value('status'));
        $this->assertSame('accepted', DB::table('trade_rfq_quotes')->where('id', $q1Id)->value('status'));
        $this->assertSame('declined', DB::table('trade_rfq_quotes')->where('id', $q2Id)->value('status'));

        // 5. Duplicate award rejected (422)
        $this->assertSame(422, $controller->accept_quote($acceptReq, $rfqId, $q2Id)->getStatusCode());

        // 6. Documented fulfilment gap: accepting a quote does NOT create an Order or PaymentRequest/escrow
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('payment_requests')->count());
    }
}
