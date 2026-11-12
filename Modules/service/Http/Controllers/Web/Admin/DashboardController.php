<?php

namespace Modules\Service\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Modules\Service\Entities\ServiceCategory;
use Modules\Service\Entities\Service;
use Modules\Service\Entities\ServiceBooking;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_categories' => ServiceCategory::count(),
            'active_categories' => ServiceCategory::where('status', 1)->count(),
            'total_services' => Service::count(),
            'active_services' => Service::where('status', 1)->count(),
            'total_bookings' => ServiceBooking::count(),
            'pending_bookings' => ServiceBooking::where('status', 'pending')->count(),
            'completed_bookings' => ServiceBooking::where('status', 'completed')->count(),
        ];

        return view('service::admin.dashboard', compact('stats'));
    }
}

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::orderBy('sort_order', 'asc')->get();
        return view('service::admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('service::admin.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'sort_order' => 'nullable|integer',
        ]);

        ServiceCategory::create($request->only(['name', 'slug', 'description', 'icon', 'status', 'sort_order']));
        return redirect()->route('admin.service.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit($id)
    {
        $category = ServiceCategory::findOrFail($id);
        return view('service::admin.categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $category = ServiceCategory::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'sort_order' => 'nullable|integer',
        ]);
        $category->update($request->only(['name', 'slug', 'description', 'icon', 'status', 'sort_order']));
        return redirect()->route('admin.service.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        ServiceCategory::findOrFail($id)->delete();
        return redirect()->route('admin.service.categories.index')->with('success', 'Category deleted.');
    }
}

class ServiceProviderController extends Controller
{
    public function index()
    {
        return view('service::admin.providers.index');
    }
}

class ServiceBookingController extends Controller
{
    public function index()
    {
        $bookings = ServiceBooking::with('service')->orderBy('created_at', 'desc')->paginate(15);
        return view('service::admin.bookings.index', compact('bookings'));
    }
}

class ReportController extends Controller
{
    public function index()
    {
        return view('service::admin.reports.index');
    }
}
