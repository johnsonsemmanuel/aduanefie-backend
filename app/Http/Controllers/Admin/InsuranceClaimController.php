<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceClaim;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InsuranceClaimController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('insurance_claims')) {
            return view('admin-views.insurance-claims.index', [
                'claims' => collect(),
                'status' => 'all',
                'counts' => ['all' => 0, 'pending' => 0, 'approved' => 0, 'paid' => 0, 'rejected' => 0],
            ]);
        }

        $status = (string) $request->query('status', 'all');
        $query = InsuranceClaim::with(['order:id,order_amount,insurance_fee,order_status', 'user:id,f_name,l_name,phone,email'])
            ->orderByDesc('id');

        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'paid', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $claims = $query->paginate(25)->appends($request->query());

        $counts = [
            'all' => InsuranceClaim::count(),
            'pending' => InsuranceClaim::where('status', 'pending')->count(),
            'approved' => InsuranceClaim::where('status', 'approved')->count(),
            'paid' => InsuranceClaim::where('status', 'paid')->count(),
            'rejected' => InsuranceClaim::where('status', 'rejected')->count(),
        ];

        return view('admin-views.insurance-claims.index', compact('claims', 'status', 'counts'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,paid',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $claim = InsuranceClaim::findOrFail($id);
        $claim->update([
            'status' => $request->input('status'),
            'admin_note' => $request->input('admin_note'),
        ]);

        Toastr::success("Insurance claim #{$claim->id} updated to {$claim->status}.");
        return back();
    }
}
