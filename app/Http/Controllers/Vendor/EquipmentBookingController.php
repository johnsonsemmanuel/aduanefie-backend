<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Item;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class EquipmentBookingController extends Controller
{
    public function index(Request $request)
    {
        $storeId = Helpers::get_store_id();
        $status = $request->query('status');

        $bookings = EquipmentBooking::with(['item', 'customer'])
            ->where('store_id', $storeId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'));

        return view('vendor-views.equipment-booking.index', compact('bookings', 'status'));
    }

    public function show($id)
    {
        $storeId = Helpers::get_store_id();

        $booking = EquipmentBooking::with([
            'item.equipment',
            'customer',
            'conditionReports',
            'extraCharges',
        ])
            ->where('store_id', $storeId)
            ->findOrFail($id);

        return view('vendor-views.equipment-booking.show', compact('booking'));
    }

    public function confirm($id)
    {
        $storeId = Helpers::get_store_id();
        $booking = EquipmentBooking::where('store_id', $storeId)->findOrFail($id);

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
        $storeId = Helpers::get_store_id();
        $booking = EquipmentBooking::where('store_id', $storeId)->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->with('error', 'Only pending or confirmed bookings can be cancelled.');
        }

        $booking->status = 'cancelled';
        $booking->save();

        return back()->with('success', 'Booking cancelled.');
    }
}
