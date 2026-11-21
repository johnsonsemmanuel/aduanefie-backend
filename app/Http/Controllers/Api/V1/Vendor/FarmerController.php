<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\GrowthUpdate;
use App\Models\CustomerProductSubscription;
use App\Models\PestReport;
use App\Models\FarmPlot;
use App\Models\AggregationHub;
use Modules\Service\Entities\Service;
use Modules\Service\Entities\ServiceBooking;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Traits\SmsGateway;

class FarmerController extends Controller
{
    use SmsGateway;

    /**
     * Farmer Dashboard - Overview of farming products
     */
    public function dashboard(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $items = Item::where('store_id', $store->id)
            ->where('is_approved', 1)
            ->where('status', 1);

        $stats = [
            'total_products' => $items->count(),
            'currently_farming' => $items->ongoingFarming()->count(),
            'harvest_ready' => $items->harvestReady()->count(),
            'backyard_farms' => $items->backyard()->count(),
            'in_stock' => $items->where('farming_status', 'in_stock')->count(),
        ];

        $farming_items = $items->ongoingFarming()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'farming_status' => $item->farming_status,
                    'is_backyard' => $item->is_backyard,
                    'price' => $item->price,
                    'unit' => $item->unit,
                    'image_full_url' => $item->image_full_url,
                    'scheduled_at' => $item->planting_date,
                    'harvest_date' => $item->expected_harvest_date,
                    'crop_cycle_days' => $item->crop_cycle_days,
                    'days_to_harvest' => $item->expected_harvest_date
                        ? \Carbon\Carbon::parse($item->expected_harvest_date)->diffInDays(\Carbon\Carbon::now())
                        : null,
                    'status_label' => ucfirst($item->farming_status),
                ];
            });

        return response()->json([
            'stats' => $stats,
            'farming_items' => $farming_items,
        ], 200);
    }

    /**
     * List farmer's products with farming filters
     */
    public function products(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $farming_status = $request->query('farming_status', 'all'); // farming, harvest_ready, in_stock, all
        $is_backyard = $request->query('is_backyard', 'all');
        $search = $request->query('search', null);
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = Item::where('store_id', $store->id)
            ->where('is_approved', 1)
            ->where('status', 1);

        if ($farming_status !== 'all') {
            $query->where('farming_status', $farming_status);
        }

        if ($is_backyard !== 'all') {
            $query->where('is_backyard', $is_backyard === 'true');
        }

        if ($search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->where('name', 'like', "%{$value}%");
                }
            });
        }

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'description' => $item->description,
                    'price' => $item->price,
                    'unit' => $item->unit,
                    'farming_status' => $item->farming_status,
                    'is_backyard' => $item->is_backyard,
                    'planting_date' => $item->planting_date,
                    'expected_harvest_date' => $item->expected_harvest_date,
                    'crop_cycle_days' => $item->crop_cycle_days,
                    'image_full_url' => $item->image_full_url,
                    'status_label' => ucfirst($item->farming_status),
                    'days_to_harvest' => $item->expected_harvest_date
                        ? \Carbon\Carbon::parse($item->expected_harvest_date)->diffInDays(\Carbon\Carbon::now())
                        : null,
                ];
            });

        return response()->json([
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Update farming status of a product
     * When status changes to harvest_ready, transition pre_order orders to ready_for_fulfillment
     * and send SMS notifications to customers
     */
    public function update_farming_status(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'farming_status' => 'required|in:farming,harvest_ready,in_stock',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        $old_status = $item->farming_status;
        $item->farming_status = $request->farming_status;
        $item->save();

        // When item becomes harvest_ready, transition all pre_order orders
        // and send SMS notifications to customers
        if ($old_status !== 'harvest_ready' && $request->farming_status === 'harvest_ready') {
            $this->transitionPreOrderOrders($item, $store, $vendor);
        }

        return response()->json([
            'message' => translate('messages.farming_status_updated'),
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'farming_status' => $item->farming_status,
                'status_label' => ucfirst($item->farming_status),
            ],
        ], 200);
    }

    /**
     * Transition pre_order orders to ready_for_fulfillment when item is harvest ready
     * and send SMS notifications to customers
     */
    protected function transitionPreOrderOrders($item, $store, $vendor)
    {
        $preOrderOrders = Order::where('store_id', $store->id)
            ->where('is_pre_order', true)
            ->where('order_status', 'pre_order')
            ->get();

        $transitionedCount = 0;

        foreach ($preOrderOrders as $order) {
            // Check if this order has this specific item
            $hasItem = $order->details()->where('item_id', $item->id)->exists();

            if ($hasItem) {
                $order->order_status = 'ready_for_fulfillment';
                $order->save();

                // Send SMS notification to customer
                $this->sendHarvestReadySms($order, $item, $store);

                $transitionedCount++;
            }
        }

        return $transitionedCount;
    }

    /**
     * Send SMS notification to customer when their pre-ordered item is harvest ready
     */
    protected function sendHarvestReadySms($order, $item, $store)
    {
        // Get customer phone number
        $phone = null;
        $customerName = 'Customer';

        if ($order->user_id) {
            $user = \App\Models\User::find($order->user_id);
            if ($user) {
                $phone = $user->phone;
                $customerName = $user->f_name ?: $user->name ?: 'Customer';
            }
        } elseif ($order->is_guest && isset($order->delivery_address)) {
            $deliveryAddress = is_string($order->delivery_address)
                ? json_decode($order->delivery_address, true)
                : $order->delivery_address;
            if (is_array($deliveryAddress) && isset($deliveryAddress['contact_person_number'])) {
                $phone = $deliveryAddress['contact_person_number'];
                $customerName = $deliveryAddress['contact_person_name'] ?? 'Customer';
            }
        }

        if (!$phone) {
            return;
        }

        $message = sprintf(
            "🌾 Great news from %s! Your %s is now harvest-ready! Order #%d is being prepared. Thank you for farming with us!",
            $store->name,
            $item->name,
            $order->id
        );

        self::intekhost($phone, $message);
    }

    /**
     * Update planting and harvest dates
     */
    public function update_dates(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'planting_date' => 'nullable|date',
            'expected_harvest_date' => 'nullable|date|after_or_equal:planting_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        if ($request->has('planting_date')) {
            $item->planting_date = $request->planting_date;
        }

        if ($request->has('expected_harvest_date')) {
            $item->expected_harvest_date = $request->expected_harvest_date;
        }

        $item->save();

        return response()->json([
            'message' => translate('messages.farming_dates_updated'),
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'planting_date' => $item->planting_date,
                'expected_harvest_date' => $item->expected_harvest_date,
                'days_to_harvest' => $item->expected_harvest_date
                    ? \Carbon\Carbon::parse($item->expected_harvest_date)->diffInDays(\Carbon\Carbon::now())
                    : null,
            ],
        ], 200);
    }

    /**
     * Update growth progress for a farming product
     * Farmer can upload photos and notes about crop progress
     */
    public function update_growth(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'progress_pct' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->where('farming_status', 'farming')
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        $growth = GrowthUpdate::create([
            'product_id' => $item->id,
            'vendor_id' => $vendor->id,
            'progress_pct' => $request->progress_pct,
            'notes' => $request->notes,
            'photos' => $request->photos ?? [],
        ]);

        // Send SMS to subscribed customers about growth update
        $subscribers = CustomerProductSubscription::forProduct($item->id)->get();
        foreach ($subscribers as $sub) {
            $message = sprintf(
                "🌱 Farm update from %s: %s is %d%% grown. Estimated harvest: %s. Stay tuned!",
                $store->name,
                $item->name,
                $request->progress_pct,
                $item->expected_harvest_date ?? 'soon'
            );
            self::intekhost($sub->phone, $message);
        }

        // Check if harvest ready and auto-update status
        if ($request->progress_pct >= 100) {
            $item->farming_status = 'harvest_ready';
            $item->save();
            $this->transitionPreOrderOrders($item, $store, $vendor);
        }

        return response()->json([
            'message' => translate('messages.growth_update_saved'),
            'growth' => [
                'id' => $growth->id,
                'product_id' => $growth->product_id,
                'progress_pct' => $growth->progress_pct,
                'notes' => $growth->notes,
                'photos' => $growth->photos,
                'created_at' => $growth->created_at,
            ],
        ], 200);
    }

    /**
     * Send SMS broadcast to all subscribers of a farming product
     */
    public function broadcast_sms(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1600',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        $subscribers = CustomerProductSubscription::forProduct($item->id)->get();
        $sent = 0;
        $failed = 0;

        foreach ($subscribers as $sub) {
            $message = sprintf(
                "🌾 Update from %s: %s\n%s",
                $store->name,
                $item->name,
                $request->message
            );
            $result = self::intekhost($sub->phone, $message);
            if ($result === 'success') {
                $sent++;
            } else {
                $failed++;
            }
        }

        return response()->json([
            'message' => translate('messages.sms_broadcast_sent'),
            'sent' => $sent,
            'failed' => $failed,
            'total_subscribers' => $subscribers->count(),
        ], 200);
    }

    /**
     * Get subscribers for a farming product
     */
    public function get_subscribers(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        $subscribers = CustomerProductSubscription::forProduct($item->id)
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'phone' => $sub->phone,
                    'subscribed_at' => $sub->subscribed_at,
                    'is_guest' => (bool) !$sub->user_id,
                ];
            });

        return response()->json([
            'subscribers' => $subscribers,
            'total' => $subscribers->count(),
        ], 200);
    }

    /**
     * Report pest infestation for a farming product
     */
    public function report_pest(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'pest_type' => 'required|string|max:100',
            'severity' => 'required|in:low,medium,high,critical',
            'description' => 'required|string|max:2000',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $item = Item::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        $report = PestReport::create([
            'product_id' => $item->id,
            'vendor_id' => $vendor->id,
            'pest_type' => $request->pest_type,
            'severity' => $request->severity,
            'description' => $request->description,
            'photos' => $request->photos ?? [],
            'status' => 'reported',
        ]);

        // Generate AI treatment recommendation
        $aiRecommendation = $this->getAiPestRecommendation($request->pest_type, $item->name, $request->severity);
        $report->ai_recommendation = $aiRecommendation;
        $report->save();

        // Notify subscribed customers about pest issue
        $subscribers = CustomerProductSubscription::forProduct($item->id)->get();
        foreach ($subscribers as $sub) {
            $message = sprintf(
                "⚠️ Pest alert from %s: %s has been affected by %s (%s). We are treating it. Harvest may be delayed.",
                $store->name,
                $item->name,
                $request->pest_type,
                ucfirst($request->severity)
            );
            self::intekhost($sub->phone, $message);
        }

        return response()->json([
            'message' => translate('messages.pest_report_submitted'),
            'report' => [
                'id' => $report->id,
                'product_id' => $report->product_id,
                'pest_type' => $report->pest_type,
                'severity' => $report->severity,
                'severity_label' => $report->severity_label,
                'status' => $report->status,
                'status_label' => $report->status_label,
                'ai_recommendation' => $report->ai_recommendation,
                'reported_at' => $report->created_at,
            ],
        ], 201);
    }

    /**
     * Get pest reports for farmer's products
     */
    public function pest_reports(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $severity = $request->query('severity', 'all');
        $status = $request->query('status', 'all');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = PestReport::where('vendor_id', $vendor->id)
            ->with('product');

        if ($severity !== 'all') {
            $query->severity($severity);
        }

        if ($status !== 'all') {
            $query->status($status);
        }

        $total = $query->count();
        $reports = $query->latest()->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'product_id' => $report->product_id,
                    'product_name' => $report->product->name ?? 'Unknown',
                    'pest_type' => $report->pest_type,
                    'severity' => $report->severity,
                    'severity_label' => $report->severity_label,
                    'description' => $report->description,
                    'photos' => $report->photos,
                    'status' => $report->status,
                    'status_label' => $report->status_label,
                    'ai_recommendation' => $report->ai_recommendation,
                    'treatment_applied' => $report->treatment_applied,
                    'treated_at' => $report->treated_at,
                    'reported_at' => $report->created_at,
                ];
            });

        return response()->json([
            'reports' => $reports,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Update pest report treatment status
     */
    public function update_pest_treatment(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:reported,treating,resolved',
            'treatment_applied' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $report = PestReport::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (!$report) {
            return response()->json([
                'errors' => [['code' => 'report_not_found', 'message' => translate('messages.report_not_found')]]
            ], 404);
        }

        $report->status = $request->status;
        if ($request->has('treatment_applied')) {
            $report->treatment_applied = $request->treatment_applied;
        }
        if ($request->status === 'resolved') {
            $report->treated_at = now();
        }
        $report->save();

        return response()->json([
            'message' => translate('messages.pest_report_updated'),
            'report' => [
                'id' => $report->id,
                'status' => $report->status,
                'status_label' => $report->status_label,
                'treatment_applied' => $report->treatment_applied,
                'treated_at' => $report->treated_at,
            ],
        ], 200);
    }

    /**
     * Get AI treatment recommendation for a pest type
     */
    public function get_ai_pest_recommendation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pest_type' => 'required|string|max:100',
            'crop_type' => 'nullable|string|max:100',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $recommendation = $this->getAiPestRecommendation(
            $request->pest_type,
            $request->crop_type ?? 'crop',
            $request->severity ?? 'medium'
        );

        $recommendedInputs = $this->getRecommendedFarmInputs($request->pest_type);

        return response()->json([
            'pest_type' => $request->pest_type,
            'crop_type' => $request->crop_type,
            'severity' => $request->severity,
            'recommendation' => $recommendation,
            'recommended_inputs' => $recommendedInputs,
        ], 200);
    }

    /**
     * Low-bandwidth SMS keyword trigger: farmers reply "READY <item_id>"
     * (or "HARVEST <item_id>") to mark their crop harvest_ready without data.
     */
    public function sms_harvest_ready(Request $request)
    {
        $from = preg_replace('/\D/', '', (string) ($request->input('from') ?? $request->input('phone') ?? ''));
        $text = trim((string) ($request->input('message') ?? $request->input('text') ?? ''));

        if ($from === '' || ! preg_match('/^(?:READY|HARVEST)\s+#?(\d+)/i', $text, $matches)) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Send "READY <product_id>" from your registered farm phone.',
            ], 422);
        }

        $itemId = (int) $matches[1];
        $item = Item::with('store')->find($itemId);
        if (! $item || ! $item->store) {
            return response()->json(['status' => 'not_found', 'message' => 'Product not found.'], 404);
        }

        $storePhone = preg_replace('/\D/', '', (string) ($item->store->phone ?? ''));
        $vendorPhone = preg_replace('/\D/', '', (string) ($item->store->vendor?->phone ?? ''));
        $fromTail = substr($from, -9);

        $authorized = ($storePhone !== '' && substr($storePhone, -9) === $fromTail)
            || ($vendorPhone !== '' && substr($vendorPhone, -9) === $fromTail);

        if (! $authorized) {
            return response()->json(['status' => 'forbidden', 'message' => 'Sender phone does not match store owner.'], 403);
        }

        $item->farming_status = 'harvest_ready';
        $item->save();

        try {
            \App\Traits\SmsGateway::send(
                $request->input('from') ?? $request->input('phone'),
                "Aduanefie: {$item->name} (#{$item->id}) is now marked HARVEST READY on the marketplace."
            );
        } catch (\Throwable $e) {
            // best effort
        }

        return response()->json([
            'status' => 'updated',
            'item_id' => $item->id,
            'farming_status' => 'harvest_ready',
        ], 200);
    }

    /**
     * Map a pest/disease type to matching approved Farm Inputs & Fertiliser
     * products so farmers can purchase treatments in 1 tap.
     */
    private function getRecommendedFarmInputs(string $pestType): array
    {
        $keywordsByPest = [
            'aphids' => ['neem', 'insecticid', 'aphid', 'soap'],
            'armyworm' => ['spinosad', 'bt', 'bacillus', 'armyworm', 'insecticid'],
            'whitefly' => ['neem', 'sticky', 'whitefly', 'insecticid'],
            'mealybug' => ['neem', 'mealybug', 'insecticid'],
            'spider mite' => ['miticid', 'neem', 'acaricid', 'mite'],
            'fruit fly' => ['pheromone', 'bait', 'trap', 'insecticid'],
            'blight' => ['copper', 'fungicid', 'mancozeb', 'blight'],
            'powdery mildew' => ['sulfur', 'sulphur', 'fungicid', 'mildew'],
            'root rot' => ['trichoderma', 'fungicid', 'bio'],
            'bacterial wilt' => ['bactericid', 'copper', 'disinfect'],
            'locust' => ['metarhizium', 'biopesticid', 'insecticid'],
        ];

        $pestLower = strtolower(trim($pestType));
        $keywords = $keywordsByPest[$pestLower] ?? ['pesticid', 'fungicid', 'insecticid', 'neem'];

        $inputModuleIds = \App\Models\Module::where('module_type', 'pharmacy')
            ->orWhere('module_name', 'like', '%Input%')
            ->orWhere('module_name', 'like', '%Fertilis%')
            ->pluck('id')
            ->all();

        $query = Item::active()
            ->when(! empty($inputModuleIds), fn ($q) => $q->whereIn('module_id', $inputModuleIds))
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('name', 'like', "%{$kw}%")
                      ->orWhere('description', 'like', "%{$kw}%");
                }
            })
            ->with('store:id,name')
            ->limit(4)
            ->get();

        return $query->map(function (Item $item) {
            return [
                'id' => $item->id,
                'slug' => $item->slug,
                'name' => $item->name,
                'price' => (float) $item->price,
                'store_name' => $item->store?->name,
                'image_full_url' => $item->image_full_url,
                'product_url' => 'https://aduanefie.com/product/' . ($item->slug ?: $item->id),
            ];
        })->values()->all();
    }

    /**
     * Get AI treatment recommendation for a pest type
     */
    private function getAiPestRecommendation(string $pestType, string $cropType, string $severity): string
    {
        $pestLower = strtolower($pestType);

        // Common pest recommendations
        $recommendations = [
            'aphids' => "Apply neem oil or insecticidal soap. Introduce ladybugs as natural predators. Spray early morning or late evening.",
            'armyworm' => "Apply Bacillus thuringiensis (Bt) or spinosad. Hand-pick larvae. Destroy crop residues after harvest.",
            'whitefly' => "Use yellow sticky traps. Apply neem oil. Encourage beneficial insects like lacewings.",
            'mealybug' => "Dab with alcohol-soaked cotton. Apply neem oil or insecticidal soap. Prune heavily infested parts.",
            'spider mite' => "Increase humidity. Apply neem oil or insecticidal soap. Use predatory mites.",
            'fruit fly' => "Use pheromone traps. Apply protein bait sprays. Remove and destroy infested fruits.",
            'blight' => "Apply copper-based fungicide. Ensure good air circulation. Avoid overhead watering.",
            'powdery mildew' => "Apply sulfur-based fungicide. Prune for air circulation. Avoid excess nitrogen.",
            'root rot' => "Improve drainage. Reduce watering. Apply beneficial fungi like Trichoderma.",
            'bacterial wilt' => "Remove and destroy infected plants. Disinfect tools. Rotate crops.",
            'locust' => "Apply biopesticides containing Metarhizium. Hand-pick and destroy. Community-wide spraying recommended.",
        ];

        $severityAdvice = [
            'low' => "Monitor closely. Apply treatment at first sign of spread.",
            'medium' => "Treat immediately. Isolate affected plants if possible.",
            'high' => "Treat urgently. Consider removing severely affected plants.",
            'critical' => "Emergency treatment required. Consider crop loss mitigation. Contact extension officer.",
        ];

        $rec = $recommendations[$pestLower] ?? "Apply appropriate pesticide for $pestType. Follow label instructions carefully. Rotate active ingredients to prevent resistance.";
        $sevAdvice = $severityAdvice[$severity] ?? $severityAdvice['medium'];

        return "$rec $sevAdvice";
    }

    /**
     * List all farm plots for the vendor's store
     */
    public function plots(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $status = $request->query('status', 'all');
        $crop_type = $request->query('crop_type', 'all');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = FarmPlot::where('store_id', $store->id);

        if ($status !== 'all') {
            $query->status($status);
        }

        if ($crop_type !== 'all') {
            $query->cropType($crop_type);
        }

        $total = $query->count();
        $plots = $query->latest()->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($plot) {
                $itemsCount = $plot->items()->count();
                return [
                    'id' => $plot->id,
                    'plot_code' => $plot->plot_code,
                    'name' => $plot->name,
                    'size_acres' => $plot->size_acres,
                    'crop_type' => $plot->crop_type,
                    'variety' => $plot->variety,
                    'planting_date' => $plot->planting_date,
                    'expected_harvest_date' => $plot->expected_harvest_date,
                    'status' => $plot->status,
                    'status_label' => $plot->status_label,
                    'soil_type' => $plot->soil_type,
                    'irrigation_method' => $plot->irrigation_method,
                    'days_to_harvest' => $plot->days_to_harvest,
                    'cultivation_progress_pct' => $plot->cultivation_progress_pct,
                    'item_count' => $itemsCount,
                    'created_at' => $plot->created_at,
                ];
            });

        return response()->json([
            'plots' => $plots,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Create a new farm plot
     */
    public function create_plot(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'plot_code' => 'required|string|max:50|unique:farm_plots,plot_code',
            'size_acres' => 'nullable|numeric|min:0',
            'crop_type' => 'nullable|string|max:100',
            'variety' => 'nullable|string|max:100',
            'planting_date' => 'nullable|date',
            'expected_harvest_date' => 'nullable|date|after_or_equal:planting_date',
            'status' => 'nullable|in:planning,planting,growing,harvest_ready,fallow',
            'soil_type' => 'nullable|string|max:100',
            'irrigation_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $plot = FarmPlot::create([
            'vendor_id' => $vendor->id,
            'store_id' => $store->id,
            'name' => $request->name,
            'plot_code' => $request->plot_code,
            'size_acres' => $request->size_acres,
            'crop_type' => $request->crop_type,
            'variety' => $request->variety,
            'planting_date' => $request->planting_date,
            'expected_harvest_date' => $request->expected_harvest_date,
            'status' => $request->status ?? 'planning',
            'soil_type' => $request->soil_type,
            'irrigation_method' => $request->irrigation_method,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => translate('messages.plot_created'),
            'plot' => [
                'id' => $plot->id,
                'plot_code' => $plot->plot_code,
                'name' => $plot->name,
                'size_acres' => $plot->size_acres,
                'crop_type' => $plot->crop_type,
                'variety' => $plot->variety,
                'planting_date' => $plot->planting_date,
                'expected_harvest_date' => $plot->expected_harvest_date,
                'status' => $plot->status,
                'status_label' => $plot->status_label,
                'days_to_harvest' => $plot->days_to_harvest,
                'cultivation_progress_pct' => $plot->cultivation_progress_pct,
            ],
        ], 201);
    }

    /**
     * Update an existing farm plot
     */
    public function update_plot(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $plot = FarmPlot::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$plot) {
            return response()->json([
                'errors' => [['code' => 'plot_not_found', 'message' => translate('messages.plot_not_found')]]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'plot_code' => 'sometimes|string|max:50|unique:farm_plots,plot_code,' . $plot->id,
            'size_acres' => 'sometimes|numeric|min:0',
            'crop_type' => 'sometimes|string|max:100',
            'variety' => 'sometimes|string|max:100',
            'planting_date' => 'sometimes|date',
            'expected_harvest_date' => 'sometimes|date|after_or_equal:planting_date',
            'status' => 'sometimes|in:planning,planting,growing,harvest_ready,fallow',
            'soil_type' => 'sometimes|string|max:100',
            'irrigation_method' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $plot->update($request->only([
            'name', 'plot_code', 'size_acres', 'crop_type', 'variety',
            'planting_date', 'expected_harvest_date', 'status',
            'soil_type', 'irrigation_method', 'notes',
        ]));

        return response()->json([
            'message' => translate('messages.plot_updated'),
            'plot' => [
                'id' => $plot->id,
                'plot_code' => $plot->plot_code,
                'name' => $plot->name,
                'size_acres' => $plot->size_acres,
                'crop_type' => $plot->crop_type,
                'variety' => $plot->variety,
                'planting_date' => $plot->planting_date,
                'expected_harvest_date' => $plot->expected_harvest_date,
                'status' => $plot->status,
                'status_label' => $plot->status_label,
                'days_to_harvest' => $plot->days_to_harvest,
                'cultivation_progress_pct' => $plot->cultivation_progress_pct,
            ],
        ], 200);
    }

    /**
     * Delete a farm plot
     */
    public function delete_plot(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $plot = FarmPlot::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$plot) {
            return response()->json([
                'errors' => [['code' => 'plot_not_found', 'message' => translate('messages.plot_not_found')]]
            ], 404);
        }

        // Check if plot has items
        $itemCount = $plot->items()->count();
        if ($itemCount > 0) {
            return response()->json([
                'errors' => [['code' => 'plot_has_items', 'message' => translate('messages.plot_has_items')]]
            ], 403);
        }

        $plot->delete();

        return response()->json([
            'message' => translate('messages.plot_deleted'),
        ], 200);
    }

    /**
     * Get items assigned to a specific plot
     */
    public function plot_items(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $plot = FarmPlot::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$plot) {
            return response()->json([
                'errors' => [['code' => 'plot_not_found', 'message' => translate('messages.plot_not_found')]]
            ], 404);
        }

        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $items = Item::where('plot_id', $id)
            ->where('is_approved', 1)
            ->where('status', 1)
            ->latest()
            ->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'farming_status' => $item->farming_status,
                    'price' => $item->price,
                    'image_full_url' => $item->image_full_url,
                    'status_label' => ucfirst($item->farming_status),
                ];
            });

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
        ], 200);
    }

    /**
     * List service categories for agro-services
     */
    public function service_categories(Request $request)
    {
        $vendor = $request['vendor'];

        $categories = \Modules\Service\Entities\ServiceCategory::where('status', 1)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'categories' => $categories,
        ], 200);
    }

    /**
     * List service offerings for agro-services
     */
    public function service_offerings(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $category_id = $request->query('category_id', null);
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = Service::where('provider_id', $vendor->id)
            ->where('status', 1);

        if ($category_id) {
            $query->where('category_id', $category_id);
        }

        $total = $query->count();
        $offerings = $query->orderBy('created_at', 'desc')
            ->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'offerings' => $offerings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Get service offering details
     */
    public function service_offering_details(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $service = Service::where('id', $id)
            ->where('provider_id', $vendor->id)
            ->first();

        if (!$service) {
            return response()->json([
                'errors' => [['code' => 'service_not_found', 'message' => translate('messages.service_not_found')]]
            ], 404);
        }

        return response()->json([
            'service' => $service,
        ], 200);
    }

    /**
     * Book an agro-service
     */
    public function book_service(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer|exists:services,id',
            'scheduled_at' => 'required|date|after:now',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $service = Service::where('id', $request->service_id)
            ->where('provider_id', $vendor->id)
            ->where('status', 1)
            ->first();

        if (!$service) {
            return response()->json([
                'errors' => [['code' => 'service_not_found', 'message' => translate('messages.service_not_found')]]
            ], 404);
        }

        $booking = ServiceBooking::create([
            'service_id' => $request->service_id,
            'provider_id' => $vendor->id,
            'user_id' => $request->user ? $request->user()->id : null,
            'guest_id' => $request->user ? null : $request->header('guest_id'),
            'scheduled_at' => $request->scheduled_at,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'notes' => $request->notes,
            'status' => 'pending',
            'total_amount' => $service->price,
            'currency' => $service->currency ?? 'GHS',
            'payment_status' => 'unpaid',
        ]);

        // Send SMS confirmation
        $phone = $request->header('guest_id') ?? ($request->user ? $request->user()->phone : null);
        if ($phone) {
            self::intekhost($phone, sprintf(
                "🌾 Service booked from %s: %s scheduled for %s. Status: Pending confirmation.",
                $store->name,
                $service->title,
                $request->scheduled_at
            ));
        }

        return response()->json([
            'message' => translate('messages.service_booked'),
            'booking' => [
                'id' => $booking->id,
                'service_id' => $booking->service_id,
                'provider_id' => $booking->provider_id,
                'scheduled_at' => $booking->scheduled_at,
                'status' => $booking->status,
                'total_amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'payment_status' => $booking->payment_status,
            ],
        ], 201);
    }

    /**
     * List farmer's service bookings
     */
    public function service_bookings(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $status = $request->query('status', 'all');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = ServiceBooking::where('provider_id', $vendor->id);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $bookings = $query->with('service')->latest()->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'bookings' => $bookings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Get service booking details
     */
    public function service_booking_details(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $booking = ServiceBooking::where('id', $id)
            ->where('provider_id', $vendor->id)
            ->with('service')
            ->first();

        if (!$booking) {
            return response()->json([
                'errors' => [['code' => 'booking_not_found', 'message' => translate('messages.booking_not_found')]]
            ], 404);
        }

        return response()->json([
            'booking' => $booking,
        ], 200);
    }

    /**
     * Cancel a service booking
     */
    public function cancel_service_booking(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $booking = ServiceBooking::where('id', $id)
            ->where('provider_id', $vendor->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'errors' => [['code' => 'booking_not_found', 'message' => translate('messages.booking_not_found')]]
            ], 404);
        }

        if (!in_array($booking->status, ['pending', 'accepted'])) {
            return response()->json([
                'errors' => [['code' => 'cannot_cancel', 'message' => translate('messages.cannot_cancel_booking')]]
            ], 403);
        }

        $booking->status = 'cancelled';
        $booking->save();

        return response()->json([
            'message' => translate('messages.booking_cancelled'),
            'booking' => $booking,
        ], 200);
    }

    /**
     * List aggregation hubs for the vendor's store
     */
    public function aggregation_hubs(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $status = $request->query('status', 'all');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $query = AggregationHub::where('store_id', $store->id);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $hubs = $query->latest()->offset(($offset - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($hub) {
                return [
                    'id' => $hub->id,
                    'hub_code' => $hub->hub_code,
                    'name' => $hub->name,
                    'address' => $hub->address,
                    'city' => $hub->city,
                    'region' => $hub->region,
                    'country_code' => $hub->country_code,
                    'latitude' => $hub->latitude,
                    'longitude' => $hub->longitude,
                    'phone' => $hub->phone,
                    'contact_person' => $hub->contact_person,
                    'capacity_kg' => $hub->capacity_kg,
                    'current_utilization_kg' => $hub->current_utilization_kg,
                    'available_capacity_kg' => $hub->available_capacity_kg,
                    'utilization_percent' => $hub->utilization_percent,
                    'status' => $hub->status,
                    'status_label' => $hub->status_label,
                    'services' => $hub->services,
                    'notes' => $hub->notes,
                ];
            });

        return response()->json([
            'hubs' => $hubs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * Create a new aggregation hub
     */
    public function create_aggregation_hub(Request $request)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        if (!$store) {
            return response()->json([
                'errors' => [['code' => 'no_store', 'message' => translate('messages.no_store_found')]]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'hub_code' => 'required|string|max:50|unique:aggregation_hubs,hub_code',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:3',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:40',
            'contact_person' => 'nullable|string|max:255',
            'capacity_kg' => 'nullable|numeric|min:0',
            'services' => 'nullable|array',
            'services.*' => 'string|in:cold_chain,sorting,grading,packaging,storage,drying',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $hub = AggregationHub::create([
            'vendor_id' => $vendor->id,
            'store_id' => $store->id,
            'name' => $request->name,
            'hub_code' => $request->hub_code,
            'address' => $request->address,
            'city' => $request->city,
            'region' => $request->region,
            'country_code' => $request->country_code ?? 'GH',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'contact_person' => $request->contact_person,
            'capacity_kg' => $request->capacity_kg,
            'current_utilization_kg' => 0,
            'status' => 'active',
            'services' => $request->services ?? [],
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => translate('messages.hub_created'),
            'hub' => [
                'id' => $hub->id,
                'hub_code' => $hub->hub_code,
                'name' => $hub->name,
                'size_acres' => $hub->size_acres,
                'capacity_kg' => $hub->capacity_kg,
                'status' => $hub->status,
                'status_label' => $hub->status_label,
            ],
        ], 201);
    }

    /**
     * Update an aggregation hub
     */
    public function update_aggregation_hub(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $hub = AggregationHub::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$hub) {
            return response()->json([
                'errors' => [['code' => 'hub_not_found', 'message' => translate('messages.hub_not_found')]]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'hub_code' => 'sometimes|string|max:50|unique:aggregation_hubs,hub_code,' . $hub->id,
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'region' => 'sometimes|string|max:100',
            'country_code' => 'sometimes|string|max:3',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:40',
            'contact_person' => 'nullable|string|max:255',
            'capacity_kg' => 'sometimes|numeric|min:0',
            'services' => 'sometimes|array',
            'services.*' => 'string|in:cold_chain,sorting,grading,packaging,storage,drying',
            'notes' => 'sometimes|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $hub->update($request->only([
            'name', 'hub_code', 'address', 'city', 'region', 'country_code',
            'latitude', 'longitude', 'phone', 'contact_person',
            'capacity_kg', 'services', 'notes',
        ]));

        return response()->json([
            'message' => translate('messages.hub_updated'),
            'hub' => [
                'id' => $hub->id,
                'hub_code' => $hub->hub_code,
                'name' => $hub->name,
                'size_acres' => $hub->size_acres,
                'capacity_kg' => $hub->capacity_kg,
                'status' => $hub->status,
                'status_label' => $hub->status_label,
            ],
        ], 200);
    }

    /**
     * Delete an aggregation hub
     */
    public function delete_aggregation_hub(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $store = $vendor->stores()->first();

        $hub = AggregationHub::where('id', $id)
            ->where('store_id', $store->id)
            ->first();

        if (!$hub) {
            return response()->json([
                'errors' => [['code' => 'hub_not_found', 'message' => translate('messages.hub_not_found')]]
            ], 404);
        }

        $hub->delete();

        return response()->json([
            'message' => translate('messages.hub_deleted'),
        ], 200);
    }
}
