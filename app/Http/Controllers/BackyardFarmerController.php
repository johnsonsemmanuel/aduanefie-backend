<?php

namespace App\Http\Controllers;

use App\Models\BackyardFarmApplication;
use App\Models\Admin;
use App\Models\Item;
use App\Models\Vendor;
use App\Models\Store;
use App\Models\User;
use App\CentralLogics\Helpers;
use App\Exports\BackyardFarmApplicationExport;
use App\Mail\BackyardFarmApplicationMail;
use App\Services\BackyardAnalyticsService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Backyard farming end-to-end layer.
 *
 * Enables individuals to apply to grow produce in their backyard and sell it
 * through the platform. The flow is:
 *
 *  1. Applicant submits a backyard farm application (size, crops, location, photos)
 *  2. A marketer verifies the farm (site visit, confirmation)
 *  3. On approval, a vendor + store is created with farm_type = 'backyard'
 *  4. The backyard farmer can add items marked as 'backyard'
 *  5. Products surface in 'nearby farming' discovery with a 'Backyard Grown' badge
 *
 * Every step is recorded so the journey from backyard soil to customer plate is
 * visible and auditable.
 */
class BackyardFarmerController extends Controller
{
    public function apply(Request $request)
    {
        $zones = \App\Models\Zone::where('status', 1)->get(['id', 'name']);

        return view('customer.backyard-farmers.apply', compact('zones'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farm_name' => 'required|string|max:255',
            'farm_size_acres' => 'required|numeric|min:0.01',
            'primary_crops' => 'required|string|max:500',
            'farming_experience' => 'nullable|string|max:1000',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'zone_id' => 'required|integer|exists:zones,id',
            'email' => 'required|email|unique:backyard_farm_applications,guest_email',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:backyard_farm_applications,guest_phone',
            'farm_photos' => 'nullable|array|max:5',
            'farm_photos.*' => 'image|mimes:webp,jpg,jpeg,png|max:2048',
            'terms_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $photos = [];
        if ($request->hasFile('farm_photos')) {
            foreach ($request->file('farm_photos') as $photo) {
                $photos[] = Helpers::upload('backyard-farms/', 'png', $photo);
            }
        }

        $application = BackyardFarmApplication::create([
            'farm_name' => $request->farm_name,
            'farm_type' => 'backyard',
            'farm_size_acres' => (float) $request->farm_size_acres,
            'primary_crops' => $request->primary_crops,
            'farming_experience' => $request->farming_experience,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'zone_id' => $request->zone_id,
            'guest_email' => $request->email,
            'guest_phone' => $request->phone,
            'farm_photos' => $photos,
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'status' => BackyardFarmApplication::STATUS_PENDING,
        ]);

        // Email confirmation to the applicant
        try {
            Mail::to($request->email)->send(new BackyardFarmApplicationMail(
                'email-templates.backyard.application-submitted',
                [
                    'farmName' => $application->farm_name,
                    'sizeAcres' => $application->farm_size_acres,
                    'primaryCrops' => $application->primary_crops,
                    'submittedAt' => $application->created_at->format('M d, Y H:i'),
                    'statusUrl' => route('backyard-farmer.status', $application->id) . '?email=' . urlencode($request->email) . '&phone=' . urlencode($request->phone),
                ]
            ));
        } catch (\Throwable $e) {
            // Email failures must not break the application flow.
        }

        Toastr::success('Your backyard farm application has been submitted. A marketer will verify your farm soon.');

        return redirect()->route('backyard-farmer.status', $application->id);
    }

    public function status(Request $request, $id)
    {
        $application = BackyardFarmApplication::findOrFail($id);

        // Only the applicant (by email/phone) or an admin can view
        $guestEmail = $request->query('email');
        $guestPhone = $request->query('phone');

        if (Auth::guard('web')->check()) {
            $application->load('user');
        } elseif ($guestEmail === $application->guest_email || $guestPhone === $application->guest_phone) {
            // Allow anonymous status check by email/phone
        } else {
            abort(403);
        }

        return view('customer.backyard-farmers.status', compact('application'));
    }

    /**
     * Admin/marketer dashboard: list pending backyard farm applications.
     */
    public function adminIndex(Request $request)
    {
        $this->authorizeSuperAdmin();

        $status = $request->input('status', 'pending');
        $query = BackyardFarmApplication::with(['verifier', 'vendor', 'store'])
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $applications = $query->paginate(20)->withQueryString();

        return view('admin-views.backyard-farmers.index', compact('applications', 'status'));
    }

    /**
     * Export backyard farm applications to CSV/Excel.
     */
    public function adminExport(Request $request)
    {
        $this->authorizeSuperAdmin();

        $status = $request->input('status', 'all');
        $query = BackyardFarmApplication::with('verifier')
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $filename = 'backyard-farm-applications-' . now()->format('Ymd-His') . '.csv';

        return Excel::download(
            new BackyardFarmApplicationExport($query),
            $filename,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    /**
     * Admin/marketer: verify a backyard farm application.
     */
    public function verify(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $application = BackyardFarmApplication::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:verify,reject',
            'verification_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $admin = Auth::guard('admin')->user();

        if ($request->input('action') === 'verify') {
            $application->update([
                'status' => BackyardFarmApplication::STATUS_VERIFIED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'verification_notes' => $request->input('verification_notes'),
            ]);

            // Email the applicant
            try {
                Mail::to($application->guest_email)->send(new BackyardFarmApplicationMail(
                    'email-templates.backyard.application-verified',
                    [
                        'farmName' => $application->farm_name,
                        'verifierName' => $admin->f_name . ' ' . $admin->l_name,
                        'verifiedAt' => now()->format('M d, Y H:i'),
                        'notes' => $application->verification_notes,
                        'statusUrl' => route('backyard-farmer.status', $application->id) . '?email=' . urlencode($application->guest_email) . '&phone=' . urlencode($application->guest_phone),
                    ]
                ));
            } catch (\Throwable $e) {
                // Email failures must not break the verification flow.
            }

            Toastr::success('Backyard farm verified. Approve it to create the vendor store.');

            return redirect()->route('admin.backyard-farmers.index');
        }

        // Reject
        $application->update([
            'status' => BackyardFarmApplication::STATUS_REJECTED,
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        // Email the applicant
        try {
            Mail::to($application->guest_email)->send(new BackyardFarmApplicationMail(
                'email-templates.backyard.application-rejected',
                [
                    'farmName' => $application->farm_name,
                    'reason' => $application->rejection_reason,
                    'applyUrl' => route('backyard-farmer.apply'),
                ]
            ));
        } catch (\Throwable $e) {
            // Email failures must not break the rejection flow.
        }

        Toastr::warning('Backyard farm application rejected.');

        return redirect()->route('admin.backyard-farmers.index');
    }

    /**
     * Admin/marketer: approve a verified backyard farm and create vendor + store.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $application = BackyardFarmApplication::where('id', $id)
            ->where('status', BackyardFarmApplication::STATUS_VERIFIED)
            ->firstOrFail();

        $admin = Auth::guard('admin')->user();

        // Create vendor user
        $vendor = Vendor::create([
            'f_name' => $application->farm_name,
            'l_name' => 'Backyard Farmer',
            'email' => $application->guest_email,
            'phone' => $application->guest_phone,
            'password' => Hash::make(Str::random(12)),
            'status' => 1,
        ]);

        // Create store
        $store = Store::create([
            'vendor_id' => $vendor->id,
            'name' => $application->farm_name,
            'email' => $application->guest_email,
            'phone' => $application->guest_phone,
            'address' => $application->address,
            'latitude' => $application->latitude,
            'longitude' => $application->longitude,
            'zone_id' => $application->zone_id,
            'farm_type' => 'backyard',
            'is_backyard' => true,
            'primary_crops' => $application->primary_crops,
            'farm_size_acres' => $application->farm_size_acres,
            'cultivation_status' => 'active',
            'status' => 1, // active
            'module_id' => 1,
        ]);

        $application->update([
            'status' => BackyardFarmApplication::STATUS_APPROVED,
            'vendor_id' => $vendor->id,
            'store_id' => $store->id,
            'converted_at' => now(),
        ]);

        // Email the applicant
        try {
            Mail::to($application->guest_email)->send(new BackyardFarmApplicationMail(
                'email-templates.backyard.application-approved',
                [
                    'farmName' => $application->farm_name,
                    'storeName' => $store->name,
                    'approvedAt' => now()->format('M d, Y H:i'),
                    'vendorLoginUrl' => route('store.login'),
                ]
            ));
        } catch (\Throwable $e) {
            // Email failures must not break the approval flow.
        }

        Toastr::success('Backyard farm approved. Vendor store created: ' . $store->name);

        return redirect()->route('admin.backyard-farmers.index');
    }

    /**
     * Dashboard for the backyard farmer (after conversion to vendor).
     */
    public function dashboard(Request $request, BackyardAnalyticsService $analytics)
    {
        $vendor = Auth::guard('vendor')->user();
        if (! $vendor) {
            return redirect()->route('store.login');
        }

        $store = $vendor->stores()->first();
        if (! $store || (string) $store->farm_type !== 'backyard') {
            abort(403);
        }

        $items = Item::where('store_id', $store->id)
            ->where('is_approved', 1)
            ->orderByDesc('id')
            ->paginate(15);

        $application = BackyardFarmApplication::where('store_id', $store->id)
            ->orWhere('vendor_id', $vendor->id)
            ->latest()
            ->first();

        $farmAnalytics = $analytics->farmAnalytics($store);

        return view('vendor.backyard-farmers.dashboard', compact('store', 'items', 'application', 'farmAnalytics'));
    }

    private function authorizeSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin || (int) $admin->role_id !== 1) {
            abort(403);
        }
    }
}
