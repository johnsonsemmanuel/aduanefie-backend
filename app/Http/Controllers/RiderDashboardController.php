<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\Order;
use App\Models\ProvideDMEarning;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only rider portal.
 *
 * There is deliberately no "update order status" action here yet. The only
 * existing implementation of that transition is
 * Api\V1\DeliverymanController@update_order_status, and it creates payment
 * transactions, applies OTP verification and moves delivery-man counters. A
 * second, hand-written copy of that logic in a web controller would be a
 * second money path with its own bugs, so the portal stops at visibility until
 * that logic is extracted and reviewed once.
 */
class RiderDashboardController extends Controller
{
    /** Statuses the mobile app treats as "current" for a rider. */
    private const CURRENT_STATUSES = ['accepted', 'confirmed', 'pending', 'processing', 'picked_up', 'handover'];

    /** Statuses that mean the rider's involvement has ended. */
    private const FINISHED_STATUSES = [
        'delivered', 'canceled', 'failed', 'refunded', 'refund_requested', 'refund_request_canceled',
    ];

    public function index()
    {
        $rider = $this->currentRider();

        $currentOrders = Order::with(['store', 'customer', 'parcel_category'])
            ->where('delivery_man_id', $rider->id)
            ->whereIn('order_status', self::CURRENT_STATUSES)
            ->dmOrder()
            ->orderBy('schedule_at')
            ->get();

        $recentOrders = Order::with(['store', 'parcel_category'])
            ->where('delivery_man_id', $rider->id)
            ->whereIn('order_status', self::FINISHED_STATUSES)
            ->dmOrder()
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return view('rider.dashboard', [
            'rider' => $rider,
            'currentOrders' => $currentOrders,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function earnings()
    {
        $rider = $this->currentRider();

        $wallet = DeliveryManWallet::where('delivery_man_id', $rider->id)->first();

        $entries = ProvideDMEarning::where('delivery_man_id', $rider->id)
            ->latest('created_at')
            ->limit(25)
            ->get();

        $deliveredCount = Order::where('delivery_man_id', $rider->id)
            ->where('order_status', 'delivered')
            ->dmOrder()
            ->count();

        return view('rider.earnings', [
            'rider' => $rider,
            'wallet' => $wallet,
            'entries' => $entries,
            'deliveredCount' => $deliveredCount,
        ]);
    }

    /**
     * The middleware has already proven the session rider is approved and
     * active; this only resolves the model instance.
     */
    private function currentRider(): DeliveryMan
    {
        return DeliveryMan::findOrFail(Auth::guard('delivery_men')->id());
    }
}
