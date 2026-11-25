<?php

namespace Tests\Feature;

use App\CentralLogics\ProductLogic;
use App\Models\Admin;
use App\Models\Item;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Store;
use App\Services\MarketerCommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CommercialWorkflowLifecycleTest extends TestCase
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
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('role_id')->default(5);
                $table->decimal('marketer_commission_rate', 8, 3)->nullable();
                $table->decimal('onboarding_bounty_ghs', 10, 2)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('zone_id')->default(2);
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(1);
                $table->string('store_business_model')->default('commission');
                $table->unsignedBigInteger('marketer_id')->nullable();
                $table->timestamp('marketer_assigned_at')->nullable();
                $table->decimal('tax', 8, 2)->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('store_id');
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->text('variations')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->string('payment_status')->default('unpaid');
                $table->string('order_status')->default('pending');
                $table->string('payment_method')->default('digital_payment');
                $table->timestamp('delivered')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('order_references')) {
            Schema::create('order_references', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('transaction_reference')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('order_transactions')) {
            Schema::create('order_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('order_id')->unique();
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->decimal('store_amount', 12, 2)->default(0);
                $table->decimal('admin_commission', 12, 2)->default(0);
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('marketer_commissions')) {
            Schema::create('marketer_commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketer_id');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('order_id')->nullable()->unique();
                $table->unsignedBigInteger('order_transaction_id')->nullable();
                $table->string('type')->default('order');
                $table->string('bounty_key')->nullable()->unique();
                $table->decimal('base_amount', 12, 2);
                $table->decimal('rate', 8, 3);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('GHS');
                $table->string('status')->default('pending');
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
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('GHS');
                $table->string('method')->default('mobile_money');
                $table->string('network')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('country', 4)->nullable();
                $table->string('status')->default('requested');
                $table->timestamp('requested_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('reference')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_inventory_stock_deduction_and_cancellation_restoration_across_item_and_variations(): void
    {
        $store = Store::create(['name' => 'Adenta Fresh Produce', 'status' => 1]);
        $item = Item::create([
            'name' => 'Grade A Crate Eggs',
            'store_id' => $store->id,
            'price' => 65.00,
            'stock' => 25,
            'variations' => json_encode([
                ['type' => 'Large-Crate', 'price' => 70, 'stock' => 10],
                ['type' => 'Medium-Crate', 'price' => 60, 'stock' => 15],
            ]),
        ]);

        // 1. Order places 4 units of Large-Crate -> decrements both variation stock and total stock
        $updated = ProductLogic::update_stock($item, 4, 'Large-Crate');
        $updated->save();

        $fresh = $item->fresh();
        $this->assertSame(21, (int) $fresh->stock);
        $vars = json_decode($fresh->variations, true);
        $this->assertSame(6, (int) $vars[0]['stock']);
        $this->assertSame(15, (int) $vars[1]['stock']);

        // 2. Order canceled before delivery -> negative quantity (-4) restores variation and total stock cleanly
        $restored = ProductLogic::update_stock($fresh, -4, 'Large-Crate');
        $restored->save();

        $afterCancel = $item->fresh();
        $this->assertSame(25, (int) $afterCancel->stock);
        $varsAfter = json_decode($afterCancel->variations, true);
        $this->assertSame(10, (int) $varsAfter[0]['stock']);
    }

    public function test_full_order_fulfilment_commission_accrual_holding_maturity_and_settlement_lifecycle(): void
    {
        $marketer = Admin::create([
            'f_name' => 'Kofi',
            'l_name' => 'Owusu',
            'email' => 'kofi.owusu@aduanefie.com',
            'role_id' => 5,
            'marketer_commission_rate' => 20.0, // 20% of platform commission
        ]);
        $financeAdmin = Admin::create([
            'f_name' => 'Finance',
            'l_name' => 'Lead',
            'email' => 'finance@aduanefie.com',
            'role_id' => 1,
        ]);
        $store = Store::create([
            'name' => 'Somanya Mango Coop',
            'status' => 1,
            'store_business_model' => 'commission',
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => Carbon::now()->subDays(30),
        ]);

        // 1. Order created & delivered today; platform earned 50.00 GHS commission on 500.00 GHS order
        $order = Order::create([
            'user_id' => 10,
            'store_id' => $store->id,
            'order_amount' => 500.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'delivered' => Carbon::now(),
        ]);
        OrderTransaction::create([
            'order_id' => $order->id,
            'order_amount' => 500.00,
            'store_amount' => 450.00,
            'admin_commission' => 50.00,
            'status' => null,
        ]);

        $service = new MarketerCommissionService();

        // 2. Accrual creates 10.00 GHS (20% of 50.00) in PENDING status with 14-day hold; re-running is idempotent
        $this->assertSame(1, $service->accrue());
        $this->assertSame(0, $service->accrue());

        $commission = MarketerCommission::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(10.00, (float) $commission->amount);
        $this->assertSame(MarketerCommission::STATUS_PENDING, $commission->status);

        // 3. Cannot request payout before 14-day holding window matures
        try {
            $service->requestPayout($marketer, [
                'network' => 'MTN',
                'account_name' => 'Kofi Owusu',
                'account_number' => '0244123456',
                'country' => 'GH',
            ]);
            $this->fail('Expected payout request before holding maturity to throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('eligible', strtolower($e->getMessage()));
        }

        // 4. Advance past 14-day holding window -> refresh() releases to ELIGIBLE -> payout requested, approved & settled
        DB::table('marketer_commissions')->where('id', $commission->id)->update([
            'eligible_at' => Carbon::now()->subMinute(),
        ]);
        $stats = $service->refresh();
        $this->assertSame(1, $stats['released']);
        $this->assertSame(MarketerCommission::STATUS_ELIGIBLE, $commission->fresh()->status);

        $payout = $service->requestPayout($marketer, [
            'network' => 'MTN',
            'account_name' => 'Kofi Owusu',
            'account_number' => '0244123456',
            'country' => 'GH',
        ]);
        $this->assertSame(10.00, (float) $payout->amount);
        $this->assertSame(MarketerCommission::STATUS_IN_PAYOUT, $commission->fresh()->status);

        $approved = $service->approve($payout, $financeAdmin);
        $this->assertSame(MarketerPayout::STATUS_APPROVED, $approved->status);

        $paid = $service->markPaid($approved, $financeAdmin, 'MOMO-TX-20261125-001');
        $this->assertSame(MarketerPayout::STATUS_PAID, $paid->status);
        $this->assertSame('MOMO-TX-20261125-001', $paid->reference);
        $this->assertSame(MarketerCommission::STATUS_PAID, $commission->fresh()->status);
    }

    public function test_refund_request_holds_and_final_refund_voids_unpaid_commission_and_reconciles_payout(): void
    {
        $marketer = Admin::create([
            'f_name' => 'Efua',
            'l_name' => 'Mensah',
            'email' => 'efua@aduanefie.com',
            'role_id' => 5,
            'marketer_commission_rate' => 25.0,
        ]);
        $store = Store::create([
            'name' => 'Aburi Yam Hub',
            'status' => 1,
            'store_business_model' => 'commission',
            'marketer_id' => $marketer->id,
            'marketer_assigned_at' => Carbon::now()->subDays(30),
        ]);

        $order = Order::create([
            'user_id' => 11,
            'store_id' => $store->id,
            'order_amount' => 400.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'delivered' => Carbon::now()->subDays(20),
        ]);
        $tx = OrderTransaction::create([
            'order_id' => $order->id,
            'order_amount' => 400.00,
            'store_amount' => 360.00,
            'admin_commission' => 40.00,
            'status' => null,
        ]);

        $service = new MarketerCommissionService();
        $this->assertSame(1, $service->accrue());
        $service->refresh(); // releases since delivered 20 days ago

        $commission = MarketerCommission::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(MarketerCommission::STATUS_ELIGIBLE, $commission->status);

        // 1. Customer opens refund_requested -> refresh() parks commission in PENDING with null eligible_at
        $order->update(['order_status' => 'refund_requested']);
        $heldStats = $service->refresh();
        $this->assertSame(1, $heldStats['held']);
        $this->assertSame(MarketerCommission::STATUS_PENDING, $commission->fresh()->status);
        $this->assertNull($commission->fresh()->eligible_at);

        // 2. Dispute resolved in store's favour (back to delivered) -> restored to ELIGIBLE
        $order->update(['order_status' => 'delivered']);
        $restoredStats = $service->refresh();
        $this->assertSame(1, $restoredStats['restored']);
        $this->assertSame(MarketerCommission::STATUS_ELIGIBLE, $commission->fresh()->status);

        // 3. Marketer requests payout, then order is refunded BEFORE payout is sent -> commission VOIDED & payout auto-rejected/zeroed
        $payout = $service->requestPayout($marketer, [
            'network' => 'MTN',
            'account_name' => 'Efua Mensah',
            'account_number' => '0244987654',
            'country' => 'GH',
        ]);
        $order->update(['order_status' => 'refunded']);
        $tx->update(['status' => 'refunded_without_delivery_charge']);

        $voidStats = $service->refresh();
        $this->assertSame(1, $voidStats['voided']);
        $this->assertSame(MarketerCommission::STATUS_VOID, $commission->fresh()->status);
        $this->assertSame('order_canceled_or_refunded', $commission->fresh()->void_reason);
        $this->assertNotSame(MarketerPayout::STATUS_PAID, $payout->fresh()->status);
    }
}
