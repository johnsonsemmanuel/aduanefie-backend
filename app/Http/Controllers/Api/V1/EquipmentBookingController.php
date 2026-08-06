<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\Models\EquipmentConditionReport;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class EquipmentBookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id'       => 'required|integer|exists:items,id',
            'start_date'    => 'required|date|after:now',
            'end_date'      => 'required|date|after:start_date',
            'duration_type' => 'required|in:hourly,daily,weekly,monthly',
        ]);

        Helpers::setZoneIds($request);
        $zoneIds = json_decode($request->header('zoneId'), true);

        $item = Item::with('equipment', 'store')->find($validated['item_id']);

        if (!$item || !$item->equipment) {
            return response()->json([
                'message' => 'Equipment not found for this item.'
            ], 404);
        }

        if (!$item->store || !in_array($item->store->zone_id, $zoneIds ?? [])) {
            return response()->json([
                'message' => 'This equipment is not available in your zone.'
            ], 422);
        }

        $equipment = $item->equipment;

        if ($equipment->status !== 'available') {
            return response()->json([
                'message' => 'This equipment is not currently available.'
            ], 422);
        }

        $rateMap = [
            'hourly'  => $equipment->hourly_rate,
            'daily'   => $equipment->daily_rate,
            'weekly'  => $equipment->weekly_rate,
            'monthly' => $equipment->monthly_rate,
        ];

        $rate = $rateMap[$validated['duration_type']] ?? null;

        if ($rate === null || $rate <= 0) {
            return response()->json([
                'message' => "This equipment does not have a {$validated['duration_type']} rate."
            ], 422);
        }

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);

        $durationValue = null;
        $durationHours = null;

        switch ($validated['duration_type']) {
            case 'hourly':
                $totalMinutes = (int) round($start->diffInMinutes($end));
                if ($totalMinutes % 60 !== 0) {
                    return response()->json([
                        'message' => 'Hourly bookings must be in whole hours.'
                    ], 422);
                }
                $durationValue = intdiv($totalMinutes, 60);
                $durationHours = $durationValue;
                break;

            case 'daily':
                $durationValue = (int) floor($start->diffInDays($end));
                if ($start->copy()->addDays($durationValue)->ne($end)) {
                    return response()->json([
                        'message' => 'Daily bookings must span whole days.'
                    ], 422);
                }
                $durationHours = $durationValue * 24;
                break;

            case 'weekly':
                $durationValue = (int) floor($start->diffInWeeks($end));
                if ($start->copy()->addWeeks($durationValue)->ne($end)) {
                    return response()->json([
                        'message' => 'Weekly bookings must span whole weeks.'
                    ], 422);
                }
                $durationHours = $durationValue * 168;
                break;

            case 'monthly':
                $durationValue = (int) floor($start->diffInMonths($end));
                if ($start->copy()->addMonths($durationValue)->ne($end)) {
                    return response()->json([
                        'message' => 'Monthly bookings must span whole months.'
                    ], 422);
                }
                $durationHours = $durationValue * 730;
                break;
        }

        if ($durationValue === null || $durationValue < 1) {
            return response()->json([
                'message' => 'Invalid rental duration for the selected date range.'
            ], 422);
        }

        if ($equipment->min_rental_duration && $durationHours < $equipment->min_rental_duration) {
            return response()->json([
                'message' => "Minimum rental duration is {$equipment->min_rental_duration} hours."
            ], 422);
        }

        if ($equipment->max_rental_duration && $durationHours > $equipment->max_rental_duration) {
            return response()->json([
                'message' => "Maximum rental duration is {$equipment->max_rental_duration} hours."
            ], 422);
        }

        $overlappingCount = EquipmentBooking::where('item_id', $item->id)
            ->whereIn('status', ['confirmed', 'active', 'overdue'])
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start)
            ->count();

        if ($overlappingCount >= $item->stock) {
            return response()->json([
                'message' => 'This equipment is fully booked for the selected period.'
            ], 422);
        }

        $totalAmount = $rate * $durationValue;
        $securityDeposit = $equipment->security_deposit;

        $booking = EquipmentBooking::create([
            'item_id'          => $item->id,
            'customer_id'      => auth('api')->id(),
            'store_id'         => $item->store_id,
            'start_date'       => $start->toDateTimeString(),
            'end_date'         => $end->toDateTimeString(),
            'duration_type'    => $validated['duration_type'],
            'duration_value'   => $durationValue,
            'total_amount'     => $totalAmount,
            'security_deposit' => $securityDeposit,
            'operator_included' => $equipment->operator_included,
            'operator_fee'     => $equipment->operator_included ? $equipment->operator_fee : null,
            'status'           => 'pending',
            'notes'            => $request->input('notes'),
        ]);

        \App\Services\EquipmentBookingNotifier::notify($booking, 'created');

        return response()->json([
            'message' => 'Booking request submitted.',
            'data'    => $booking->load(['item.equipment', 'store']),
        ], 201);
    }

    public function index(Request $request)
    {
        $bookings = EquipmentBooking::with(['item.equipment', 'store'])
            ->where('customer_id', auth('api')->id())
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'));

        return response()->json($bookings);
    }

    public function show($id)
    {
        $booking = EquipmentBooking::with([
            'item.equipment',
            'store',
            'conditionReports',
            'extraCharges',
        ])
            ->where('customer_id', auth('api')->id())
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    public function cancel($id)
    {
        $booking = EquipmentBooking::where('customer_id', auth('api')->id())->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Only pending or confirmed bookings can be cancelled.'
            ], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        \App\Services\EquipmentBookingNotifier::notify($booking, 'cancelled', 'customer');

        return response()->json([
            'message' => 'Booking cancelled.',
            'data'    => $booking,
        ], 200);
    }

    public function browse(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $zones = json_decode($zone_id, true);

        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);

        $query = Item::active($zones, null, null, true)
            ->whereHas('equipment', function ($q) use ($request) {
                $q->where('status', 'available')
                    ->when($request->filled('min_price'), function ($q) use ($request) {
                        $q->whereRaw('COALESCE(daily_rate, hourly_rate) >= ?', [(float) $request->min_price]);
                    })
                    ->when($request->filled('max_price'), function ($q) use ($request) {
                        $q->whereRaw('COALESCE(daily_rate, hourly_rate) <= ?', [(float) $request->max_price]);
                    });
            })
            ->when(config('module.current_module_data'), function ($q) {
                $q->whereHas('store', function ($store) {
                    $store->where('module_id', config('module.current_module_data')['id']);
                });
            })
            ->when($request->filled('store_id'), function ($q) use ($request) {
                $q->where('store_id', $request->store_id);
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('category', function ($category) use ($request) {
                    $category->where('id', $request->category_id)->orWhere('parent_id', $request->category_id);
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('description', 'like', "%{$request->search}%")
                        ->orWhereHas('translations', function ($t) use ($request) {
                            $t->where('key', 'name')->where('value', 'like', "%{$request->search}%");
                        });
                });
            })
            ->with(['equipment', 'translations', 'store'])
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        $equipmentMap = collect($query->items())->mapWithKeys(function ($item) {
            return $item->equipment ? [$item->id => self::formatEquipment($item->equipment)] : [];
        });

        $products = Helpers::product_data_formatting($query->items(), true, false, app()->getLocale());

        $products = collect($products)->map(function ($product) use ($equipmentMap) {
            $product['equipment'] = $equipmentMap[$product['id']] ?? null;
            return $product;
        })->values()->toArray();

        return response()->json([
            'total_size' => $query->total(),
            'limit' => (int) $limit,
            'offset' => (int) $offset,
            'products' => $products,
        ], 200);
    }

    public function showEquipment(Request $request, $id)
    {
        Helpers::setZoneIds($request);
        $zones = json_decode($request->header('zoneId'), true);

        $item = Item::active($zones, null, null, true)
            ->with(['equipment', 'translations', 'store'])
            ->when(config('module.current_module_data'), function ($q) {
                $q->whereHas('store', function ($store) {
                    $store->where('module_id', config('module.current_module_data')['id']);
                });
            })
            ->when(is_numeric($id), function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->when(!is_numeric($id), function ($q) use ($id) {
                $q->where('slug', $id);
            })
            ->first();

        if (!$item || !$item->equipment || $item->equipment->status !== 'available') {
            return response()->json([
                'message' => 'Equipment not found or not available in your zone.'
            ], 404);
        }

        $product = Helpers::product_data_formatting($item, false, false, app()->getLocale());
        $product['equipment'] = self::formatEquipment($item->equipment);

        return response()->json(['data' => $product], 200);
    }

    public function submitConditionReport(Request $request, $id)
    {
        $validated = $request->validate([
            'report_type'      => 'required|in:pre_rental,post_rental',
            'condition_rating' => 'required|integer|between:1,5',
            'notes'            => 'nullable|string|max:1000',
            'images'           => 'nullable|array|max:5',
            'images.*'         => 'image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $booking = EquipmentBooking::where('customer_id', auth('api')->id())->findOrFail($id);

        if (EquipmentConditionReport::where('booking_id', $booking->id)
            ->where('report_type', $validated['report_type'])->exists()) {
            return response()->json([
                'message' => 'A condition report for this stage already exists.'
            ], 422);
        }

        if ($validated['report_type'] === 'pre_rental' && !in_array($booking->status, ['confirmed', 'active'])) {
            return response()->json([
                'message' => 'Pre-rental report can only be submitted for a confirmed or active booking.'
            ], 422);
        }

        if ($validated['report_type'] === 'post_rental' && !in_array($booking->status, ['active', 'overdue', 'completed'])) {
            return response()->json([
                'message' => 'Post-rental report can only be submitted for an active, overdue or completed booking.'
            ], 422);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if ($image && $image->isValid()) {
                    $imagePaths[] = Storage::disk('public')->put('equipment/condition-reports', $image);
                }
            }
        }

        $report = EquipmentConditionReport::create([
            'booking_id'       => $booking->id,
            'report_type'      => $validated['report_type'],
            'reported_by'      => 'customer',
            'condition_rating' => $validated['condition_rating'],
            'notes'            => $validated['notes'] ?? null,
            'images'           => $imagePaths ?: null,
            'created_at'       => now(),
        ]);

        return response()->json([
            'message' => 'Condition report submitted.',
            'data'    => $report,
        ], 201);
    }

    private static function formatEquipment(Equipment $equipment): array
    {
        return [
            'id'                 => $equipment->id,
            'hourly_rate'        => (float) $equipment->hourly_rate,
            'daily_rate'         => (float) $equipment->daily_rate,
            'weekly_rate'        => (float) $equipment->weekly_rate,
            'monthly_rate'       => (float) $equipment->monthly_rate,
            'security_deposit'   => (float) $equipment->security_deposit,
            'min_rental_duration'=> $equipment->min_rental_duration,
            'max_rental_duration'=> $equipment->max_rental_duration,
            'requires_delivery'  => (bool) $equipment->requires_delivery,
            'self_pickup'        => (bool) $equipment->self_pickup,
            'operator_included'  => (bool) $equipment->operator_included,
            'operator_fee'       => (float) $equipment->operator_fee,
            'condition_notes'    => $equipment->condition_notes,
            'status'             => $equipment->status,
        ];
    }
}
