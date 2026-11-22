<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Empty-shape readers for the v4.2 storefront's marketing modules.
 *
 * The React storefront calls GET /bundle/home, /bogo/home and
 * /happy-hour/running on every home page. This backend is an older build and
 * has no bundle, BOGO or happy-hour tables, models, admin screens or cart
 * logic, so there is no data these endpoints could ever return. A 404 makes
 * the browser log an error on every load; the storefront already hides each
 * section when the payload is empty, so return the shape it expects and let
 * it hide the section.
 *
 * These are deliberate stubs, not a feature. Replace the bodies with real
 * queries when the modules land; the keys below are the ones the storefront
 * actually reads (bundles, is_live, is_running).
 */
class PromotionController extends Controller
{
    public function bundle_home(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }

        return response()->json([
            'data' => [],
            'bundles' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $limit,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
            'total_size' => 0,
            'limit' => $limit,
            'offset' => 1,
        ], 200);
    }

    public function bogo_home(Request $request)
    {
        return response()->json([
            'is_live' => false,
            'offer' => null,
            'description' => null,
            'bundles' => [],
        ], 200);
    }

    public function happy_hour_running(Request $request)
    {
        return response()->json([
            'is_running' => false,
            'happy_hour' => null,
        ], 200);
    }

    public function happy_hour_stores(Request $request)
    {
        return response()->json([
            'data' => [],
            'stores' => [],
        ], 200);
    }

    public function pro_customer_active_offer(Request $request)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pro_customer_subscriptions')) {
            return response()->json(['status' => false, 'benefit' => null], 200);
        }

        $user = $request->user();
        $sub = \Illuminate\Support\Facades\DB::table('pro_customer_subscriptions')
            ->join('pro_customer_plans', 'pro_customer_plans.id', '=', 'pro_customer_subscriptions.plan_id')
            ->where('pro_customer_subscriptions.user_id', $user->id)
            ->where('pro_customer_subscriptions.status', 'active')
            ->where('pro_customer_subscriptions.end_at', '>', now())
            ->orderByDesc('pro_customer_subscriptions.id')
            ->select([
                'pro_customer_subscriptions.*',
                'pro_customer_plans.name as plan_name',
                'pro_customer_plans.tagline',
                'pro_customer_plans.benefit_type',
                'pro_customer_plans.offer_type',
                'pro_customer_plans.charge_discount_percentage',
                'pro_customer_plans.min_order_status',
                'pro_customer_plans.min_order_amount',
            ])
            ->first();

        if (! $sub) {
            return response()->json(['status' => false, 'benefit' => null], 200);
        }

        $daysRemaining = max(0, now()->diffInDays(\Carbon\Carbon::parse($sub->end_at), false));

        return response()->json([
            'status' => true,
            'plan_details' => [
                'id' => $sub->id,
                'plan_id' => $sub->plan_id,
                'plan_name' => $sub->plan_name,
                'start_at' => $sub->start_at,
                'end_at' => $sub->end_at,
                'days_remaining' => $daysRemaining,
                'payment_method' => $sub->payment_method,
                'total_saved' => 0,
                'total_orders' => 0,
            ],
            'benefit' => [
                'type' => $sub->benefit_type,
                'offer_type' => $sub->offer_type,
                'charge_discount_percentage' => (float) $sub->charge_discount_percentage,
                'min_order_status' => (int) $sub->min_order_status,
                'min_order_amount' => (float) $sub->min_order_amount,
            ],
        ], 200);
    }

    public function pro_customer_plans(Request $request)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pro_customer_plans')) {
            return response()->json(['plans' => []], 200);
        }

        $plans = \Illuminate\Support\Facades\DB::table('pro_customer_plans')
            ->where('status', true)
            ->orderBy('price')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'plan_name' => $p->name,
                    'tagline' => $p->tagline,
                    'price' => (float) $p->price,
                    'validity_days' => (int) $p->validity_days,
                    'duration_days' => (int) $p->validity_days,
                    'benefit' => [
                        'type' => $p->benefit_type,
                        'offer_type' => $p->offer_type,
                        'charge_discount_percentage' => (float) $p->charge_discount_percentage,
                        'min_order_status' => (int) $p->min_order_status,
                        'min_order_amount' => (float) $p->min_order_amount,
                    ],
                ];
            })
            ->values();

        return response()->json(['plans' => $plans], 200);
    }

    public function pro_customer_subscribe(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'plan_id' => 'required|integer',
            'payment_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => \App\CentralLogics\Helpers::error_processor($validator)], 422);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pro_customer_plans') || ! \Illuminate\Support\Facades\Schema::hasTable('pro_customer_subscriptions')) {
            return response()->json(['message' => 'Subscriptions are not migrated yet.'], 503);
        }

        $plan = \Illuminate\Support\Facades\DB::table('pro_customer_plans')
            ->where('id', (int) $request->input('plan_id'))
            ->where('status', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Selected membership plan was not found.'], 404);
        }

        $user = $request->user();
        $price = (float) $plan->price;
        $paymentMethod = strtolower((string) ($request->input('payment_method') ?: 'wallet'));

        if ($paymentMethod === 'paystack' && $price > 0) {
            $subId = \Illuminate\Support\Facades\DB::table('pro_customer_subscriptions')->insertGetId([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'payment_method' => 'paystack',
                'amount_paid' => $price,
                'start_at' => now(),
                'end_at' => now()->addDays((int) ($plan->validity_days ?: 30)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $currency = \App\Models\BusinessSetting::where(['key' => 'currency'])->first()?->value ?? 'GHS';
            $storeLogo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first();
            $additionalData = [
                'business_name' => \App\Models\BusinessSetting::where(['key' => 'business_name'])->first()?->value ?? 'Aduanefie',
                'business_logo' => \App\CentralLogics\Helpers::get_full_url(
                    'business',
                    $storeLogo?->value,
                    $storeLogo?->storage[0]?->value ?? 'public'
                ),
            ];

            $payer = new \App\Library\Payer(
                trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')) ?: ($user->email ?? 'Customer'),
                $user->email ?: 'customer@aduanefie.com',
                $user->phone ?: '',
                ''
            );

            $paymentInfo = new \App\Library\PaymentInfo(
                success_hook: 'pro_membership_activate',
                failure_hook: 'pro_membership_fail',
                currency_code: $currency,
                payment_method: 'paystack',
                payment_platform: $request->input('payment_platform', 'web'),
                payer_id: $user->id,
                receiver_id: '100',
                additional_data: $additionalData,
                payment_amount: $price,
                external_redirect_link: $request->input('callback'),
                attribute: 'pro_membership',
                attribute_id: $subId
            );

            $receiverInfo = new \App\Library\Receiver('Aduanefie', 'example.png');
            $redirectLink = \App\Library\Payment::generate_link($payer, $paymentInfo, $receiverInfo);

            return response()->json([
                'message' => 'Redirecting to Paystack checkout.',
                'payment_method' => 'paystack',
                'subscription_id' => $subId,
                'redirect_link' => $redirectLink,
            ], 200);
        }

        if ($paymentMethod !== 'wallet') {
            return response()->json([
                'message' => 'Unsupported payment method for Pro membership. Choose Wallet or Paystack.',
            ], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $plan, $price) {
            $lockedUser = \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((float) $lockedUser->wallet_balance < $price) {
                return response()->json([
                    'message' => 'Insufficient wallet balance. Top up your Aduanefie Wallet to activate this plan.',
                ], 422);
            }

            if ($price > 0) {
                \App\CentralLogics\CustomerLogic::create_wallet_transaction(
                    $lockedUser->id,
                    $price,
                    'pro_membership',
                    (string) $plan->id
                );
            }

            \Illuminate\Support\Facades\DB::table('pro_customer_subscriptions')
                ->where('user_id', $lockedUser->id)
                ->where('status', 'active')
                ->update(['status' => 'expired', 'updated_at' => now()]);

            $startAt = now();
            $endAt = now()->addDays((int) ($plan->validity_days ?: 30));

            $subId = \Illuminate\Support\Facades\DB::table('pro_customer_subscriptions')->insertGetId([
                'user_id' => $lockedUser->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'payment_method' => 'wallet',
                'amount_paid' => $price,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => "Activated {$plan->name}!",
                'subscription_id' => $subId,
                'end_at' => $endAt->toIso8601String(),
            ], 200);
        });
    }

    public function pro_customer_cancel(Request $request)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pro_customer_subscriptions')) {
            return response()->json(['message' => 'No active subscription found.'], 404);
        }

        $updated = \Illuminate\Support\Facades\DB::table('pro_customer_subscriptions')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'No active subscription found.'], 404);
        }

        return response()->json(['message' => 'Your Pro membership has been cancelled.'], 200);
    }

    public function pro_customer_faqs(Request $request)
    {
        return response()->json([
            'faqs' => [
                [
                    'question' => 'What does Aduanefie Pro include?',
                    'answer' => 'Free local farm delivery on qualifying orders, priority harvest-ready alerts, and early access to regional group buys.',
                ],
                [
                    'question' => 'How is membership paid?',
                    'answer' => 'Membership is billed cleanly from your Aduanefie Wallet with zero automatic recurring surprise charges.',
                ],
                [
                    'question' => 'Can I cancel anytime?',
                    'answer' => 'Yes — you can cancel anytime from your Subscription Plan page.',
                ],
            ],
        ], 200);
    }

    public function pro_customer_terms(Request $request)
    {
        return response()->json([
            'terms' => 'Aduanefie Pro benefits apply to eligible local farm deliveries above the plan minimum order amount and exclude cross-border freight.',
        ], 200);
    }

    public function delivery_charge_coverage_list(Request $request)
    {
        return response()->json([
            'type' => null,
            'coverage' => [],
        ], 200);
    }

    public function trending_searches(Request $request)
    {
        $module_id = $request->header('moduleId');
        $items = \App\Models\Item::active()
            ->when($module_id, fn ($q) => $q->where('module_id', $module_id))
            ->orderByDesc('order_count')
            ->limit(8)
            ->pluck('name')
            ->values();

        return response()->json($items, 200);
    }

    public function exclusive_deals_stores(Request $request)
    {
        return response()->json([
            'stores' => [],
            'total_size' => 0,
        ], 200);
    }
}
