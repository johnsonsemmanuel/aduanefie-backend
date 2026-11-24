<?php

namespace Modules\Service\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Modules\Service\Entities\ServiceCategory;
use Modules\Service\Entities\Service;
use Modules\Service\Entities\ServiceBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function categoryList(Request $request)
    {
        $categories = ServiceCategory::where('status', 1)->orderBy('sort_order', 'asc')->get();
        return response()->json(['categories' => $categories], 200);
    }

    public function serviceList(Request $request)
    {
        $query = Service::with('category')->where('status', 1);
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $services = $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc')->paginate(15);
        return response()->json(['services' => $services], 200);
    }

    public function serviceDetails($id)
    {
        $service = Service::with('category')->find($id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }
        return response()->json(['service' => $service], 200);
    }

    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer',
            'scheduled_at' => 'required|date',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $service = Service::find($request->service_id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }

        $user = auth('api')->user();
        $guestId = $request->input('guest_id');
        $userId = $user ? $user->id : null;

        $booking = ServiceBooking::create([
            'service_id' => $request->service_id,
            'user_id' => $userId,
            'guest_id' => $guestId,
            'provider_id' => $service->provider_id,
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

        return response()->json(['booking' => $booking, 'message' => 'Service booking created'], 200);
    }

    public function bookingList(Request $request)
    {
        $user = auth('api')->user();
        $guestId = $request->input('guest_id');

        $query = ServiceBooking::with('service');
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($guestId) {
            $query->where('guest_id', $guestId);
        } else {
            return response()->json(['errors' => ['Authentication or guest_id required']], 403);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json(['bookings' => $bookings], 200);
    }

    public function bookingDetails($id)
    {
        $booking = ServiceBooking::with('service')->find($id);
        if (!$booking) {
            return response()->json(['errors' => ['Booking not found']], 404);
        }
        return response()->json(['booking' => $booking], 200);
    }

    public function cancelBooking(Request $request, $id)
    {
        $booking = ServiceBooking::find($id);
        if (!$booking) {
            return response()->json(['errors' => ['Booking not found']], 404);
        }

        $booking->status = 'cancelled';
        $booking->cancelled_at = now();
        $booking->cancellation_reason = $request->input('reason');
        $booking->save();

        return response()->json(['booking' => $booking, 'message' => 'Booking cancelled'], 200);
    }
}
