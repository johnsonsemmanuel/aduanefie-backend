<?php

namespace App\Http\Controllers\Admin;

use App\Models\Item;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\Models\EquipmentExtraCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class EquipmentBookingController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $bookings = EquipmentBooking::with(['item', 'customer', 'store'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'));

        return view('admin-views.equipment-booking.index', compact('bookings', 'status'));
    }

    public function show($id)
    {
        $booking = EquipmentBooking::with([
            'item.equipment',
            'customer',
            'store',
            'conditionReports',
            'extraCharges',
        ])->findOrFail($id);

        return view('admin-views.equipment-booking.show', compact('booking'));
    }

    public function confirm($id)
    {
        $booking = EquipmentBooking::findOrFail($id);

        if ($booking->status !== 'pending') {
            return back()->with('error', 'Only pending bookings can be confirmed.');
        }

        DB::transaction(function () use ($booking) {
            $item = Item::where('id', $booking->item_id)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                throw new \Exception('Item not found.');
            }

            $hasOverdue = EquipmentBooking::where('item_id', $item->id)
                ->where('status', 'overdue')
                ->exists();

            if ($hasOverdue) {
                throw new \Exception(
                    'This equipment has an overdue booking and cannot be confirmed until it is returned.'
                );
            }

            $overlappingCount = EquipmentBooking::where('item_id', $item->id)
                ->whereIn('status', ['confirmed', 'active', 'overdue'])
                ->where('id', '!=', $booking->id)
                ->where('start_date', '<', $booking->end_date)
                ->where('end_date', '>', $booking->start_date)
                ->count();

            if ($overlappingCount >= $item->stock) {
                throw new \Exception(
                    'Not enough units available for this period.'
                );
            }

            $booking->status = 'confirmed';
            $booking->save();
        });

        return back()->with('success', 'Booking confirmed.');
    }

    public function cancel($id)
    {
        $booking = EquipmentBooking::findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->with('error', 'Only pending or confirmed bookings can be cancelled.');
        }

        $booking->status = 'cancelled';
        $booking->save();

        return back()->with('success', 'Booking cancelled.');
    }

    public function markActive($id)
    {
        $booking = EquipmentBooking::findOrFail($id);

        if ($booking->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed bookings can be marked active (picked up).');
        }

        $booking->status = 'active';
        $booking->save();

        return back()->with('success', 'Booking activated. Equipment picked up.');
    }

    public function markReturned(Request $request, $id)
    {
        $request->validate([
            'late_fee' => 'nullable|numeric|min:0',
            'late_fee_description' => 'nullable|string|max:255',
        ]);

        $booking = EquipmentBooking::findOrFail($id);

        if (!in_array($booking->status, ['active', 'overdue'])) {
            return back()->with('error', 'Only active or overdue bookings can be marked returned.');
        }

        DB::transaction(function () use ($booking, $request) {
            $booking->status = 'completed';
            $booking->save();

            $lateFee = $request->input('late_fee', 0);
            if ($lateFee > 0) {
                EquipmentExtraCharge::create([
                    'booking_id' => $booking->id,
                    'charge_type' => 'late_fee',
                    'amount' => $lateFee,
                    'description' => $request->input('late_fee_description', 'Late return fee'),
                ]);
            }
        });

        return back()->with('success', 'Equipment returned. Booking completed.');
    }
}
