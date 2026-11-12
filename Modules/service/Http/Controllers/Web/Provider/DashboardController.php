<?php

namespace Modules\Service\Http\Controllers\Web\Provider;

use App\Http\Controllers\Controller;
use Modules\Service\Entities\Service;
use Modules\Service\Entities\ServiceBooking;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $providerId = auth('api')->user() ? auth('api')->user()->id : null;

        $stats = [
            'total_services' => Service::where('provider_id', $providerId)->count(),
            'active_services' => Service::where('provider_id', $providerId)->where('status', 1)->count(),
            'total_bookings' => ServiceBooking::where('provider_id', $providerId)->count(),
            'pending_bookings' => ServiceBooking::where('provider_id', $providerId)->where('status', 'pending')->count(),
            'completed_bookings' => ServiceBooking::where('provider_id', $providerId)->where('status', 'completed')->count(),
        ];

        return view('service::provider.dashboard', compact('stats'));
    }
}

class ServiceOfferingController extends Controller
{
    public function index()
    {
        $providerId = auth('api')->user() ? auth('api')->user()->id : null;
        $services = Service::where('provider_id', $providerId)->orderBy('created_at', 'desc')->get();
        return view('service::provider.offerings.index', compact('services'));
    }
}

class ServiceBookingController extends Controller
{
    public function index()
    {
        $providerId = auth('api')->user() ? auth('api')->user()->id : null;
        $bookings = ServiceBooking::with('service')->where('provider_id', $providerId)->orderBy('created_at', 'desc')->paginate(15);
        return view('service::provider.bookings.index', compact('bookings'));
    }
}
