<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\RegionalTour;
use App\Models\RegionalTourRegistration;
use App\Traits\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class RegionalTourController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('regional_tours')) {
            return response()->json([
                'tours' => [],
                'regions' => RegionalTour::GHANA_REGIONS,
                'demand_by_region' => [],
            ], 200);
        }

        $region = trim((string) $request->query('region', ''));

        $tours = RegionalTour::query()
            ->withCount('registrations')
            ->with(['leadMarketer:id,f_name,l_name,phone'])
            ->whereIn('status', [
                RegionalTour::STATUS_UPCOMING,
                RegionalTour::STATUS_OPEN,
                RegionalTour::STATUS_IN_PROGRESS,
            ])
            ->when($region !== '', fn ($q) => $q->where('region', $region))
            ->orderBy('start_date')
            ->get()
            ->map(function (RegionalTour $tour) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'region' => $tour->region,
                    'district' => $tour->district,
                    'venue' => $tour->venue,
                    'start_date' => optional($tour->start_date)->toDateString(),
                    'end_date' => optional($tour->end_date)->toDateString(),
                    'capacity' => $tour->capacity,
                    'registrations_count' => (int) $tour->registrations_count,
                    'spots_left' => max(0, (int) $tour->capacity - (int) $tour->registrations_count),
                    'status' => $tour->status,
                    'notes' => $tour->notes,
                    'lead_marketer' => $tour->leadMarketer
                        ? trim($tour->leadMarketer->f_name . ' ' . $tour->leadMarketer->l_name)
                        : null,
                ];
            });

        $demand = Schema::hasTable('regional_tour_registrations')
            ? RegionalTourRegistration::select('region', DB::raw('COUNT(*) as requests_count'), DB::raw('SUM(farmer_count) as total_farmers'))
                ->groupBy('region')
                ->orderByDesc('requests_count')
                ->get()
            : collect();

        return response()->json([
            'tours' => $tours,
            'regions' => RegionalTour::GHANA_REGIONS,
            'demand_by_region' => $demand,
        ], 200);
    }

    public function register(Request $request)
    {
        if (! Schema::hasTable('regional_tour_registrations')) {
            return response()->json([
                'errors' => [['code' => 'unavailable', 'message' => 'Regional tours are not ready yet.']],
            ], 503);
        }

        // The route is auth:api, but fail closed here as well: without a
        // resolved account there is no application, even if the middleware is
        // later relaxed or this controller is reached from another entry point.
        $user = $request->user('api');
        if (! $user) {
            return response()->json([
                'errors' => [['code' => 'auth', 'message' => 'Sign in to your account before applying for a regional tour.']],
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'tour_id' => 'nullable|integer|exists:regional_tours,id',
            'farmer_name' => 'required|string|max:150',
            'phone' => 'required|string|min:9|max:30',
            'email' => 'nullable|email|max:150',
            'region' => 'required|string|max:80',
            'community' => 'nullable|string|max:150',
            'farm_size_acres' => 'nullable|numeric|min:0|max:100000',
            'primary_crops' => 'nullable|string|max:500',
            'is_cooperative' => 'nullable|boolean',
            'farmer_count' => 'nullable|integer|min:1|max:5000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'marketer_code' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tour = null;
        if ($request->filled('tour_id')) {
            $tour = RegionalTour::withCount('registrations')->find($request->input('tour_id'));
            if (! $tour || ! in_array($tour->status, [RegionalTour::STATUS_OPEN, RegionalTour::STATUS_UPCOMING, RegionalTour::STATUS_IN_PROGRESS], true)) {
                return response()->json([
                    'errors' => [['code' => 'tour', 'message' => 'This regional tour is no longer accepting registrations.']],
                ], 403);
            }
            if ($tour->capacity > 0 && $tour->registrations_count >= $tour->capacity) {
                return response()->json([
                    'errors' => [['code' => 'capacity', 'message' => 'This tour stop is currently full. You can still submit a region visit request.']],
                ], 403);
            }
        }

        if ($tour && RegionalTourRegistration::where('user_id', $user->id)
            ->where('tour_id', $tour->id)
            ->exists()) {
            return response()->json([
                'errors' => [['code' => 'duplicate', 'message' => 'You have already applied for this regional tour.']],
            ], 409);
        }

        // Resolve optional marketer attribution (strictly role_id = 5)
        $marketerId = $tour?->lead_marketer_id;
        if ($request->filled('marketer_code')) {
            $rawCode = trim((string) $request->input('marketer_code'));
            $roleId = (int) config('marketer.role_id', 5);
            $marketer = null;
            if (Schema::hasColumn('admins', 'marketer_code')) {
                $marketer = Admin::where('role_id', $roleId)
                    ->whereRaw('LOWER(marketer_code) = ?', [strtolower($rawCode)])
                    ->first();
            }
            if (! $marketer && preg_match('/^(?:MKT-)?(\d+)$/i', $rawCode, $m)) {
                $marketer = Admin::where('id', (int) $m[1])->where('role_id', $roleId)->first();
            }
            if ($marketer) {
                $marketerId = $marketer->id;
            }
        }

        $registration = RegionalTourRegistration::create([
            'user_id' => $user->id,
            'tour_id' => $tour?->id,
            'farmer_name' => trim($request->input('farmer_name')),
            'phone' => trim($request->input('phone')),
            'email' => $request->input('email') ? trim($request->input('email')) : null,
            'region' => $tour ? $tour->region : trim($request->input('region')),
            'community' => $request->input('community') ? trim($request->input('community')) : null,
            'farm_size_acres' => $request->filled('farm_size_acres') ? (float) $request->input('farm_size_acres') : null,
            'primary_crops' => $request->input('primary_crops') ? trim($request->input('primary_crops')) : null,
            'is_cooperative' => (bool) $request->boolean('is_cooperative', false),
            'farmer_count' => max(1, (int) $request->input('farmer_count', 1)),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'marketer_id' => $marketerId,
            'status' => RegionalTourRegistration::STATUS_REGISTERED,
            'notes' => $request->input('notes') ? trim($request->input('notes')) : null,
        ]);

        // Best-effort SMS confirmation via IntekHost / configured SMS gateway
        try {
            $smsBody = $tour
                ? "Aduanefie Regional Tour: Hi {$registration->farmer_name}, you are registered for {$tour->title} ({$tour->region}) on " . optional($tour->start_date)->format('M d, Y') . ". Our field team looks forward to meeting you."
                : "Aduanefie Regional Tour: Hi {$registration->farmer_name}, we received your farm visit request for {$registration->region}. Our regional marketer team will reach out when a tour stop is scheduled.";
            SmsGateway::send($registration->phone, $smsBody);
        } catch (\Throwable $e) {
            // SMS failures must not block farmer registration.
        }

        return response()->json([
            'data' => $registration,
            'message' => $tour
                ? 'Registered for the regional tour stop. You will receive an SMS confirmation shortly.'
                : 'Your regional visit request has been received. Our regional team will contact you when a tour is scheduled.',
        ], 201);
    }
}
