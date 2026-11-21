<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\RegionalTour;
use App\Models\RegionalTourRegistration;
use App\Traits\SmsGateway;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegionalTourController extends Controller
{
    public function index(Request $request)
    {
        $region = $request->query('region', '');

        $tours = RegionalTour::with(['leadMarketer:id,f_name,l_name'])
            ->withCount('registrations')
            ->when($region !== '', fn ($q) => $q->where('region', $region))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        $registrations = RegionalTourRegistration::with(['tour:id,title,region', 'marketer:id,f_name,l_name'])
            ->when($region !== '', fn ($q) => $q->where('region', $region))
            ->latest('id')
            ->paginate(25, ['*'], 'reg_page')
            ->withQueryString();

        $demandByRegion = RegionalTourRegistration::select(
            'region',
            DB::raw('COUNT(*) as requests_count'),
            DB::raw('SUM(farmer_count) as total_farmers'),
            DB::raw("SUM(CASE WHEN status = 'onboarded' THEN 1 ELSE 0 END) as onboarded_count")
        )
            ->groupBy('region')
            ->orderByDesc('requests_count')
            ->get();

        $marketers = Admin::where('role_id', (int) config('marketer.role_id', 5))
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'email']);

        $regions = RegionalTour::GHANA_REGIONS;

        return view('admin-views.regional-tours.index', compact(
            'tours',
            'registrations',
            'demandByRegion',
            'marketers',
            'regions',
            'region'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'region' => 'required|string|max:80',
            'district' => 'nullable|string|max:100',
            'venue' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'lead_marketer_id' => 'nullable|integer|exists:admins,id',
            'capacity' => 'nullable|integer|min:1|max:10000',
            'status' => 'nullable|in:upcoming,open,in_progress,completed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        RegionalTour::create([
            'title' => $data['title'],
            'region' => $data['region'],
            'district' => $data['district'] ?? null,
            'venue' => $data['venue'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'lead_marketer_id' => $data['lead_marketer_id'] ?? null,
            'capacity' => (int) ($data['capacity'] ?? 100),
            'status' => $data['status'] ?? RegionalTour::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);

        Toastr::success('Regional tour scheduled successfully.');
        return back();
    }

    public function updateStatus(Request $request, int $id)
    {
        $tour = RegionalTour::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:upcoming,open,in_progress,completed,cancelled',
        ]);
        $tour->update(['status' => $data['status']]);

        Toastr::success('Tour status updated.');
        return back();
    }

    public function broadcastReminder(Request $request, int $id)
    {
        $tour = RegionalTour::with('registrations')->findOrFail($id);
        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            $message = "Reminder: Aduanefie Regional Tour ({$tour->title}) in {$tour->region} is on " . optional($tour->start_date)->format('M d, Y') . ($tour->venue ? " at {$tour->venue}" : '') . ". Bring your farm details to get onboarded!";
        }

        $sent = 0;
        foreach ($tour->registrations as $reg) {
            if (! $reg->phone) {
                continue;
            }
            try {
                SmsGateway::send($reg->phone, $message);
                $sent++;
            } catch (\Throwable $e) {
                // continue best-effort
            }
        }

        Toastr::success("SMS reminder sent to {$sent} registered farmer(s).");
        return back();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $region = $request->query('region', '');
        $rows = RegionalTourRegistration::with(['tour:id,title', 'marketer:id,f_name,l_name'])
            ->when($region !== '', fn ($q) => $q->where('region', $region))
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Tour', 'Farmer Name', 'Phone', 'Email', 'Region', 'Community',
                'Farm Size (Acres)', 'Primary Crops', 'Cooperative', 'Farmer Count',
                'Marketer', 'Status', 'Checked In At', 'Created At',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->id,
                    $r->tour?->title ?? 'Open Visit Request',
                    $r->farmer_name,
                    $r->phone,
                    $r->email ?? '-',
                    $r->region,
                    $r->community ?? '-',
                    $r->farm_size_acres ?? '-',
                    $r->primary_crops ?? '-',
                    $r->is_cooperative ? 'Yes' : 'No',
                    $r->farmer_count,
                    $r->marketer ? trim($r->marketer->f_name . ' ' . $r->marketer->l_name) : '-',
                    $r->status,
                    optional($r->checked_in_at)->toDateTimeString() ?? '-',
                    optional($r->created_at)->toDateTimeString() ?? '-',
                ]);
            }
            fclose($handle);
        }, 'regional-tour-registrations-' . now()->format('Y-m-d') . '.csv');
    }
}
