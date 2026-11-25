<?php

namespace {
    if (!function_exists('test_counting_order_place_hook')) {
        function test_counting_order_place_hook($data)
        {
            $GLOBALS['__test_hook_call_count'] = ($GLOBALS['__test_hook_call_count'] ?? 0) + 1;
            order_place($data);
        }
    }

    if (!function_exists('test_flaky_downstream_hook')) {
        function test_flaky_downstream_hook($data)
        {
            if (!empty($GLOBALS['__flaky_should_fail'])) {
                throw new \RuntimeException('Simulated downstream DB timeout');
            }
            order_place($data);
        }
    }
}

namespace Tests\Feature {

use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\MarketerPortalController;
use App\Http\Controllers\PaystackController;
use App\Models\Admin;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderTransaction;
use App\Models\PaymentRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\MarketerAccessService;
use App\Services\MarketerCommissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BatchAFinancialProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Model::unguard();
        $this->createCoreTables();
    }

    private function createCoreTables(): void
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

        if (!Schema::hasTable('addon_settings')) {
            Schema::create('addon_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key_name')->nullable();
                $table->string('settings_type')->nullable();
                $table->string('mode')->nullable();
                $table->text('live_values')->nullable();
                $table->text('test_values')->nullable();
                $table->boolean('is_active')->default(true);
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

        if (!Schema::hasTable('order_references')) {
            Schema::create('order_references', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('reference_number')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('phone')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(0);
                $table->integer('active')->default(1);
                $table->decimal('tax', 8, 2)->default(0);
                $table->decimal('minimum_order', 10, 2)->default(0);
                $table->decimal('per_km_shipping_charge', 10, 2)->default(0);
                $table->decimal('minimum_shipping_charge', 10, 2)->default(0);
                $table->boolean('free_delivery')->default(false);
                $table->boolean('sub_self_delivery')->default(false);
                $table->string('store_business_model')->default('commission');
                $table->unsignedBigInteger('marketer_id')->nullable();
                $table->timestamp('marketer_assigned_at')->nullable();
                $table->boolean('marketer_verified')->default(false);
                $table->timestamp('marketer_verified_at')->nullable();
                $table->string('cultivation_status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(1);
                $table->decimal('price', 10, 2)->default(50);
                $table->integer('stock')->default(100);
                $table->boolean('marketer_verified')->default(false);
                $table->timestamp('marketer_verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('module_id')->default(1);
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->decimal('partially_paid_amount', 12, 2)->default(0);
                $table->decimal('delivery_charge', 10, 2)->default(0);
                $table->decimal('original_delivery_charge', 10, 2)->default(0);
                $table->decimal('total_tax_amount', 10, 2)->default(0);
                $table->decimal('dm_tips', 10, 2)->default(0);
                $table->decimal('additional_charge', 10, 2)->default(0);
                $table->decimal('extra_packaging_amount', 10, 2)->default(0);
                $table->decimal('coupon_discount_amount', 10, 2)->default(0);
                $table->decimal('store_discount_amount', 10, 2)->default(0);
                $table->decimal('flash_admin_discount_amount', 10, 2)->default(0);
                $table->decimal('flash_store_discount_amount', 10, 2)->default(0);
                $table->decimal('ref_bonus_amount', 10, 2)->default(0);
                $table->string('order_status')->default('pending');
                $table->string('payment_status')->default('unpaid');
                $table->string('payment_method')->default('digital_payment');
                $table->string('order_type')->default('delivery');
                $table->boolean('is_guest')->default(false);
                $table->text('delivery_address')->nullable();
                $table->string('otp')->nullable();
                $table->timestamp('confirmed')->nullable();
                $table->timestamp('delivered')->nullable();
                $table->timestamp('failed')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_payments')) {
            Schema::create('order_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('transaction_ref')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('payment_status')->default('unpaid');
                $table->string('payment_method')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_transactions')) {
            Schema::create('order_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->unsignedBigInteger('order_id');
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->decimal('store_amount', 12, 2)->default(0);
                $table->decimal('admin_commission', 12, 2)->default(0);
                $table->decimal('delivery_charge', 10, 2)->default(0);
                $table->decimal('original_delivery_charge', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->string('received_by')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('module_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payment_requests')) {
            Schema::create('payment_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('payer_id')->nullable();
                $table->string('receiver_id')->nullable();
                $table->decimal('payment_amount', 12, 2)->default(0);
                $table->string('gateway_callback_url')->nullable();
                $table->string('success_hook')->nullable();
                $table->string('failure_hook')->nullable();
                $table->string('transaction_id')->nullable();
                $table->string('currency_code', 10)->default('GHS');
                $table->string('payment_method', 50)->nullable();
                $table->text('additional_data')->nullable();
                $table->boolean('is_paid')->default(false);
                $table->text('payer_information')->nullable();
                $table->string('external_redirect_link')->nullable();
                $table->text('receiver_information')->nullable();
                $table->string('attribute_id')->nullable();
                $table->string('attribute')->nullable();
                $table->string('payment_platform')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketer_commissions')) {
            Schema::create('marketer_commissions', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->default('order');
                $table->unsignedBigInteger('marketer_id');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('order_transaction_id')->nullable();
                $table->string('bounty_key', 191)->nullable()->unique();
                $table->decimal('base_amount', 12, 2)->default(0);
                $table->decimal('rate', 8, 3)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 8)->default('GHS');
                $table->string('status', 24)->default('pending');
                $table->timestamp('eligible_at')->nullable();
                $table->unsignedBigInteger('payout_id')->nullable();
                $table->string('void_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketer_payouts')) {
            Schema::create('marketer_payouts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketer_id');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 8)->default('GHS');
                $table->string('method', 32)->default('mobile_money');
                $table->string('network', 32)->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('country', 8)->nullable();
                $table->string('status', 24)->default('requested');
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    private function makePaystackControllerWithFakeData(array $gatewayResponse): PaystackController
    {
        return new class(new PaymentRequest(), new User(), $gatewayResponse) extends PaystackController {
            public function __construct(PaymentRequest $payment, User $user, private array $fakeResponse)
            {
                parent::__construct($payment, $user);
            }

            protected function fetchPaymentData(): array
            {
                return $this->fakeResponse;
            }
        };
    }

    public function test_paystack_callback_marks_order_paid_and_is_idempotent_on_duplicate_callbacks(): void
    {
        $order = Order::create([
            'id' => 100501,
            'user_id' => 10,
            'store_id' => 1,
            'order_amount' => 150.00,
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'digital_payment',
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 150.00,
            'payment_status' => 'unpaid',
            'payment_method' => 'digital_payment',
        ]);

        $paymentId = (string) Str::uuid();
        DB::table('payment_requests')->insert([
            'id' => $paymentId,
            'payer_id' => '10',
            'payment_amount' => 150.00,
            'currency_code' => 'GHS',
            'payment_method' => 'paystack',
            'success_hook' => 'test_counting_order_place_hook',
            'failure_hook' => 'order_failed',
            'attribute' => 'order',
            'attribute_id' => (string) $order->id,
            'is_paid' => 0,
            'payment_platform' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $GLOBALS['__test_hook_call_count'] = 0;

        $controller = $this->makePaystackControllerWithFakeData([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PSK-REF-100501',
                'amount' => 15000,
                'currency' => 'GHS',
                'metadata' => [
                    'payment_id' => $paymentId,
                    'orderID' => $order->id,
                ],
            ],
        ]);

        $request = Request::create('/payment/paystack/callback', 'GET', ['trxref' => 'PSK-REF-100501']);

        // First callback
        $controller->handleGatewayCallback($request);
        // Sequential duplicate callback (e.g. webhook + browser redirect race)
        $controller->handleGatewayCallback($request);

        $this->assertSame(1, $GLOBALS['__test_hook_call_count'], 'Success hook must execute exactly once across duplicate callbacks.');
        $this->assertSame(1, (int) PaymentRequest::find($paymentId)->is_paid);
        $this->assertSame('PSK-REF-100501', PaymentRequest::find($paymentId)->transaction_id);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
    }

    public function test_paystack_callback_rejects_underpaid_amount_and_wrong_currency(): void
    {
        $order = Order::create([
            'id' => 100502,
            'user_id' => 11,
            'store_id' => 1,
            'order_amount' => 250.00,
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'digital_payment',
        ]);

        $paymentId = (string) Str::uuid();
        DB::table('payment_requests')->insert([
            'id' => $paymentId,
            'payer_id' => '11',
            'payment_amount' => 250.00,
            'currency_code' => 'GHS',
            'payment_method' => 'paystack',
            'success_hook' => 'order_place',
            'failure_hook' => 'order_failed',
            'attribute' => 'order',
            'attribute_id' => (string) $order->id,
            'is_paid' => 0,
            'payment_platform' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Underpaid: 100.00 GHS instead of 250.00 GHS
        $underpaidController = $this->makePaystackControllerWithFakeData([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PSK-UNDERPAID',
                'amount' => 10000,
                'currency' => 'GHS',
                'metadata' => ['payment_id' => $paymentId, 'orderID' => $order->id],
            ],
        ]);

        $underpaidController->handleGatewayCallback(Request::create('/payment/paystack/callback', 'GET'));

        $this->assertSame(0, (int) PaymentRequest::find($paymentId)->is_paid);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Wrong currency: NGN instead of GHS
        $wrongCurrencyController = $this->makePaystackControllerWithFakeData([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PSK-WRONGCURR',
                'amount' => 25000,
                'currency' => 'NGN',
                'metadata' => ['payment_id' => $paymentId, 'orderID' => $order->id],
            ],
        ]);

        $wrongCurrencyController->handleGatewayCallback(Request::create('/payment/paystack/callback', 'GET'));

        $this->assertSame(0, (int) PaymentRequest::find($paymentId)->is_paid);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_failed_downstream_hook_rolls_back_is_paid_and_allows_safe_retry(): void
    {
        $order = Order::create([
            'id' => 100503,
            'user_id' => 12,
            'store_id' => 1,
            'order_amount' => 80.00,
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'digital_payment',
        ]);

        $paymentId = (string) Str::uuid();
        DB::table('payment_requests')->insert([
            'id' => $paymentId,
            'payer_id' => '12',
            'payment_amount' => 80.00,
            'currency_code' => 'GHS',
            'payment_method' => 'paystack',
            'success_hook' => 'test_flaky_downstream_hook',
            'failure_hook' => 'order_failed',
            'attribute' => 'order',
            'attribute_id' => (string) $order->id,
            'is_paid' => 0,
            'payment_platform' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $GLOBALS['__flaky_should_fail'] = true;

        $controller = $this->makePaystackControllerWithFakeData([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PSK-RETRY-80',
                'amount' => 8000,
                'currency' => 'GHS',
                'metadata' => ['payment_id' => $paymentId, 'orderID' => $order->id],
            ],
        ]);

        // First attempt throws inside hook -> transaction rolls back so payment is not stranded
        $controller->handleGatewayCallback(Request::create('/payment/paystack/callback', 'GET'));
        $this->assertSame(0, (int) PaymentRequest::find($paymentId)->is_paid);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Retry succeeds cleanly
        $GLOBALS['__flaky_should_fail'] = false;
        $controller->handleGatewayCallback(Request::create('/payment/paystack/callback', 'GET'));
        $this->assertSame(1, (int) PaymentRequest::find($paymentId)->is_paid);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
    }

    public function test_marketer_cannot_unlock_bounty_by_self_verifying_an_unapproved_store(): void
    {
        $marketer = Admin::create([
            'f_name' => 'Kwame',
            'l_name' => 'Mensah',
            'email' => 'kwame@aduanefie.com',
            'role_id' => 5,
            'marketer_code' => 'MKT-ASH-01',
            'onboarding_bounty_ghs' => 25.00,
        ]);

        $unapprovedStore = Store::create([
            'name' => 'Ejura Unapproved Farm',
            'status' => 0, // Not yet approved by Admin
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => now(),
            'marketer_verified' => true, // Field verified by marketer
        ]);

        $service = new MarketerCommissionService();

        $this->assertFalse($service->storeIsVerified($unapprovedStore));
        $this->assertFalse($service->creditBounty($marketer, $unapprovedStore));
        $this->assertSame(0, $service->syncBounties($marketer));
        $this->assertSame(0, MarketerCommission::where('marketer_id', $marketer->id)->count());

        // Once Admin approves the store (status = 1), the bounty becomes eligible exactly once
        $unapprovedStore->status = 1;
        $unapprovedStore->save();

        $this->assertTrue($service->creditBounty($marketer, $unapprovedStore->fresh()));
        // Duplicate attempt is ignored
        $this->assertFalse($service->creditBounty($marketer, $unapprovedStore->fresh()));
        $this->assertSame(1, MarketerCommission::where('marketer_id', $marketer->id)->where('type', 'bounty')->count());
    }

    public function test_configurable_bounty_policy_supports_active_listing_and_completed_order_gates(): void
    {
        Config::set('marketer.bounty_policy.require_admin_approval', true);
        Config::set('marketer.bounty_policy.require_active_listing', true);
        Config::set('marketer.bounty_policy.require_completed_order', true);

        $marketer = Admin::create([
            'f_name' => 'Ama',
            'l_name' => 'Osei',
            'email' => 'ama@aduanefie.com',
            'role_id' => 5,
            'onboarding_bounty_ghs' => 25.00,
        ]);

        $store = Store::create([
            'name' => 'Bono Maize Co-op',
            'status' => 1,
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => now(),
        ]);

        $service = new MarketerCommissionService();

        // Approved store, but no active listing yet
        $this->assertFalse($service->creditBounty($marketer, $store));

        // Add active listing, still no completed order
        DB::table('items')->insert([
            'name' => 'Yellow Maize (50kg Bag)',
            'store_id' => $store->id,
            'status' => 1,
            'price' => 380,
            'stock' => 40,
        ]);
        $this->assertFalse($service->creditBounty($marketer, $store));

        // Complete a delivered order -> now qualifies
        Order::create([
            'store_id' => $store->id,
            'order_amount' => 380,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'delivered' => now(),
        ]);
        $this->assertTrue($service->creditBounty($marketer, $store));

        $summary = $service->summaryFor($marketer);
        $this->assertSame(1, $summary['stores']);
        $this->assertSame(1, $summary['approved_stores']);
        $this->assertSame(1, $summary['active_listing_stores']);
        $this->assertSame(1, $summary['transacting_stores']);

        Config::set('marketer.bounty_policy.require_active_listing', false);
        Config::set('marketer.bounty_policy.require_completed_order', false);
    }

    public function test_payout_approval_rejects_suspended_or_unapproved_bounty_store_and_prevents_double_request(): void
    {
        $marketer = Admin::create([
            'f_name' => 'Kofi',
            'l_name' => 'Mensah',
            'email' => 'kofi@aduanefie.com',
            'role_id' => 5,
            'onboarding_bounty_ghs' => 25.00,
        ]);
        $admin = Admin::create([
            'f_name' => 'Super',
            'l_name' => 'Admin',
            'email' => 'admin@aduanefie.com',
            'role_id' => 1,
        ]);

        $store = Store::create([
            'name' => 'Techiman Yam Growers',
            'status' => 1,
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => now(),
        ]);

        $service = new MarketerCommissionService();
        $this->assertTrue($service->creditBounty($marketer, $store));

        $payout = $service->requestPayout($marketer, [
            'country' => 'GH',
            'network' => 'MTN',
            'account_name' => 'Kofi Mensah',
            'account_number' => '0244123456',
        ]);

        $this->assertSame(25.00, (float) $payout->amount);

        // Concurrent/second payout request fails because eligible commissions are now locked in_payout
        $this->expectException(RuntimeException::class);
        $service->requestPayout($marketer, [
            'country' => 'GH',
            'network' => 'MTN',
            'account_name' => 'Kofi Mensah',
            'account_number' => '0244123456',
        ]);
    }

    public function test_suspended_store_bounty_cannot_be_approved_for_payout(): void
    {
        $marketer = Admin::create([
            'f_name' => 'Yaw',
            'l_name' => 'Boateng',
            'email' => 'yaw@aduanefie.com',
            'role_id' => 5,
            'onboarding_bounty_ghs' => 25.00,
        ]);
        $admin = Admin::create([
            'f_name' => 'Super',
            'l_name' => 'Admin',
            'email' => 'admin2@aduanefie.com',
            'role_id' => 1,
        ]);

        $store = Store::create([
            'name' => 'Suspended Farm',
            'status' => 1,
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => now(),
        ]);

        $service = new MarketerCommissionService();
        $service->creditBounty($marketer, $store);
        $payout = $service->requestPayout($marketer, [
            'country' => 'GH',
            'network' => 'MTN',
            'account_name' => 'Yaw Boateng',
            'account_number' => '0244999888',
        ]);

        // Store is suspended / unapproved before payout approval
        $store->status = 0;
        $store->save();

        $this->assertCount(1, $service->auditPrematureBounties());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not administratively approved');
        $service->approve($payout, $admin);
    }

    public function test_delivery_serviceability_enforces_max_radius_and_allowed_retail_zones(): void
    {
        $allowedStore = Store::create([
            'name' => 'Accra Fresh Farm',
            'zone_id' => 2,
            'status' => 1,
            'tax' => 0,
        ]);
        $outOfZoneStore = Store::create([
            'name' => 'Distant Country Zone Farm',
            'zone_id' => 115,
            'status' => 1,
            'tax' => 0,
        ]);

        DB::table('business_settings')->updateOrInsert(
            ['key' => 'max_retail_delivery_distance_km'],
            ['value' => '35']
        );
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'retail_delivery_zone_ids'],
            ['value' => json_encode([2, 9, 10])]
        );

        $controller = new OrderController();

        // 1. Within radius (12 km) and allowed zone (zone 2) -> 200 OK
        $validReq = Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'delivery',
            'order_amount' => 120,
            'distance' => 12,
            'store_id' => $allowedStore->id,
        ]);
        $validRes = $controller->checkout_summary($validReq);
        $this->assertSame(200, $validRes->getStatusCode());

        // 2. Exceeds max delivery radius (60 km > 35 km) -> 403 out_of_coverage
        $farReq = Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'delivery',
            'order_amount' => 120,
            'distance' => 60,
            'store_id' => $allowedStore->id,
        ]);
        $farRes = $controller->checkout_summary($farReq);
        $this->assertSame(403, $farRes->getStatusCode());
        $this->assertSame('distance', $farRes->getData(true)['errors'][0]['code']);

        // 3. Store in unapproved retail delivery zone (zone 115) -> 403 out_of_coverage
        $unserviceableZoneReq = Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'delivery',
            'order_amount' => 120,
            'distance' => 8,
            'store_id' => $outOfZoneStore->id,
        ]);
        $unserviceableRes = $controller->checkout_summary($unserviceableZoneReq);
        $this->assertSame(403, $unserviceableRes->getStatusCode());
        $this->assertSame('coordinates', $unserviceableRes->getData(true)['errors'][0]['code']);

        // 4. Takeaway / Farm pickup from out-of-delivery-zone farm still works (200 OK)
        $pickupReq = Request::create('/api/v1/customer/order/checkout-summary', 'POST', [
            'order_type' => 'take_away',
            'order_amount' => 120,
            'distance' => 0,
            'store_id' => $outOfZoneStore->id,
        ]);
        $pickupRes = $controller->checkout_summary($pickupReq);
        $this->assertSame(200, $pickupRes->getStatusCode());
    }
}
}
