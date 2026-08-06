<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
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
            'duration_value' => 'required|integer|min:1',
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
        $durationHours = $end->diffInHours($start);

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

        $totalAmount = $rate * $validated['duration_value'];
        $securityDeposit = $equipment->security_deposit;

        $booking = EquipmentBooking::create([
            'item_id'          => $item->id,
            'customer_id'      => auth('api')->id(),
            'store_id'         => $item->store_id,
            'start_date'       => $validated['start_date'],
            'end_date'         => $validated['end_date'],
            'duration_type'    => $validated['duration_type'],
            'duration_value'   => $validated['duration_value'],
            'total_amount'     => $totalAmount,
            'security_deposit' => $securityDeposit,
            'operator_included' => $equipment->operator_included,
            'operator_fee'     => $equipment->operator_included ? $equipment->operator_fee : null,
            'status'           => 'pending',
            'notes'            => $request->input('notes'),
        ]);

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
}
