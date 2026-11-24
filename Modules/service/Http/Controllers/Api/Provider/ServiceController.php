<?php

namespace Modules\Service\Http\Controllers\Api\Provider;

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

    public function categoryListAll(Request $request)
    {
        $categories = ServiceCategory::orderBy('sort_order', 'asc')->get();
        return response()->json(['categories' => $categories], 200);
    }

    public function createCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $category = ServiceCategory::create($request->only([
            'name', 'slug', 'description', 'icon', 'status', 'sort_order',
        ]));

        return response()->json(['category' => $category], 200);
    }

    public function editCategory($id)
    {
        $category = ServiceCategory::find($id);
        if (!$category) {
            return response()->json(['errors' => ['Category not found']], 404);
        }
        return response()->json(['category' => $category], 200);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = ServiceCategory::find($id);
        if (!$category) {
            return response()->json(['errors' => ['Category not found']], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'sort_order' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $category->update($request->only([
            'name', 'slug', 'description', 'icon', 'status', 'sort_order',
        ]));

        return response()->json(['category' => $category], 200);
    }

    public function toggleCategoryStatus($id)
    {
        $category = ServiceCategory::find($id);
        if (!$category) {
            return response()->json(['errors' => ['Category not found']], 404);
        }

        $category->status = $category->status == 1 ? 0 : 1;
        $category->save();

        return response()->json(['category' => $category, 'message' => 'Category status updated'], 200);
    }

    public function deleteCategory($id)
    {
        $category = ServiceCategory::find($id);
        if (!$category) {
            return response()->json(['errors' => ['Category not found']], 404);
        }

        $category->delete();
        return response()->json(['message' => 'Category deleted'], 200);
    }

    public function serviceList(Request $request)
    {
        $query = Service::with('category');
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $services = $query->where('status', 1)->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc')->paginate(15);
        return response()->json(['services' => $services], 200);
    }

    public function editService($id)
    {
        $service = Service::with('category')->find($id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }
        return response()->json(['service' => $service], 200);
    }

    public function createService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'unit' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'thumbnail' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $providerId = auth('api')->user() ? auth('api')->user()->id : $request->input('provider_id');

        $service = Service::create(array_merge($request->only([
            'category_id', 'title', 'slug', 'description', 'short_description',
            'price', 'currency', 'unit', 'duration_minutes', 'status', 'is_featured', 'thumbnail',
        ]), ['provider_id' => $providerId]));

        return response()->json(['service' => $service], 200);
    }

    public function updateService(Request $request, $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|integer',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'duration_minutes' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $service->update($request->only([
            'category_id', 'title', 'slug', 'description', 'short_description',
            'price', 'currency', 'unit', 'duration_minutes', 'status', 'is_featured', 'thumbnail',
        ]));

        return response()->json(['service' => $service], 200);
    }

    public function toggleServiceStatus($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }

        $service->status = $service->status == 1 ? 0 : 1;
        $service->save();

        return response()->json(['service' => $service, 'message' => 'Service status updated'], 200);
    }

    public function deleteService($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json(['errors' => ['Service not found']], 404);
        }

        $service->delete();
        return response()->json(['message' => 'Service deleted'], 200);
    }

    public function bookingList(Request $request)
    {
        $providerId = auth('api')->user() ? auth('api')->user()->id : null;
        $query = ServiceBooking::with('service')->where('provider_id', $providerId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
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

    public function acceptBooking(Request $request, $id)
    {
        $booking = ServiceBooking::find($id);
        if (!$booking) {
            return response()->json(['errors' => ['Booking not found']], 404);
        }

        $booking->status = 'accepted';
        $booking->save();

        return response()->json(['booking' => $booking, 'message' => 'Booking accepted'], 200);
    }

    public function completeBooking(Request $request, $id)
    {
        $booking = ServiceBooking::find($id);
        if (!$booking) {
            return response()->json(['errors' => ['Booking not found']], 404);
        }

        $booking->status = 'completed';
        $booking->completed_at = now();
        $booking->save();

        return response()->json(['booking' => $booking, 'message' => 'Booking completed'], 200);
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

    public function profile(Request $request)
    {
        $user = auth('api')->user();
        return response()->json(['provider' => $user], 200);
    }

    public function profileUpdate(Request $request)
    {
        $user = auth('api')->user();
        $user->update($request->only(['name', 'email', 'phone', 'description']));
        return response()->json(['provider' => $user, 'message' => 'Profile updated'], 200);
    }
}
