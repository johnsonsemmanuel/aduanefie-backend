<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BulkPricingTier;
use App\Models\StandingOrder;
use App\Models\Item;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class BulkTradeController extends Controller
{
    public function itemTiers(Request $request, $itemId)
    {
        $item = Item::findOrFail($itemId);
        $tiers = $item->bulkPricingTiers()->get();

        return response()->json([
            'success' => true,
            'item' => $item,
            'tiers' => $tiers,
        ], 200);
    }

    public function calculateBulkPrice(Request $request, $itemId)
    {
        $item = Item::findOrFail($itemId);
        $quantity = (int) $request->quantity;
        $pricing = $item->getBulkPrice($quantity);

        return response()->json([
            'success' => true,
            'item_id' => $itemId,
            'quantity' => $quantity,
            'base_price' => $pricing['price'],
            'discount_percent' => $pricing['discount_percent'],
            'effective_price' => $pricing['effective_price'],
            'total_price' => round($pricing['effective_price'] * $quantity, 2),
        ], 200);
    }

    public function createStandingOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:20',
            'frequency' => 'required|in:weekly,monthly,quarterly,seasonal',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'destination_country' => 'required|string|max:100',
            'incoterm' => 'required|in:EXW,FOB,CIF,DAP',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $item = Item::find($request->item_id);
        $user = $request->user();
        $buyerType = $user instanceof \App\Models\User ? 'customer' : 'vendor';

        $nextOrderDate = $this->calculateNextOrderDate($request->start_date, $request->frequency);

        $standingOrder = StandingOrder::create([
            'buyer_id' => $user->id,
            'buyer_type' => $buyerType,
            'item_id' => $request->item_id,
            'store_id' => $item->store_id,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'destination_country' => $request->destination_country,
            'incoterm' => $request->incoterm,
            'status' => 'active',
            'next_order_date' => $nextOrderDate,
        ]);

        return response()->json([
            'success' => true,
            'standing_order' => $standingOrder,
        ], 201);
    }

    public function listStandingOrders(Request $request)
    {
        $user = $request->user();
        $buyerType = $user instanceof \App\Models\User ? 'customer' : 'vendor';

        $orders = StandingOrder::where('buyer_id', $user->id)
            ->where('buyer_type', $buyerType)
            ->with(['item', 'store'])
            ->latest()
            ->paginate(config('default_pagination', 15));

        return response()->json([
            'success' => true,
            'standing_orders' => $orders,
        ], 200);
    }

    public function cancelStandingOrder(Request $request, $orderId)
    {
        $order = StandingOrder::findOrFail($orderId);

        $user = $request->user();
        if (!$user || $order->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['success' => true], 200);
    }

    public function dueStandingOrders(Request $request)
    {
        $orders = StandingOrder::due()->with(['item', 'store', 'buyer'])->get();

        return response()->json([
            'success' => true,
            'due_orders' => $orders,
        ], 200);
    }

    private function calculateNextOrderDate(string $startDate, string $frequency): string
    {
        $date = \Carbon\Carbon::parse($startDate);

        return match ($frequency) {
            'weekly' => $date->copy()->addWeek()->toDateString(),
            'monthly' => $date->copy()->addMonth()->toDateString(),
            'quarterly' => $date->copy()->addMonths(3)->toDateString(),
            'seasonal' => $date->copy()->addMonths(3)->toDateString(),
            default => $date->copy()->addMonth()->toDateString(),
        };
    }
}
