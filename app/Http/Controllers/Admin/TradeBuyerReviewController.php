<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\TradeBuyerAuditLog;
use App\Models\TradeBuyerProfile;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeBuyerReviewController extends Controller
{
    private function checkAdminPermission(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (! auth('admin')->check() || ! Helpers::module_permission_check('settings')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized admin action. Reviewer permission required.',
                    'code' => 'admin_unauthorized',
                ], 403);
            }
            Toastr::error(translate('messages.access_denied'));
            return null;
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($deny = $this->checkAdminPermission($request)) {
            return $deny;
        }

        $query = TradeBuyerProfile::with(['user', 'approver'])->orderByDesc('id');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('applicant_name', 'like', $term)
                    ->orWhere('organization_name', 'like', $term)
                    ->orWhere('contact_phone', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('country_of_operation', 'like', $term);
            });
        }

        $profiles = $query->paginate($request->input('limit', 25));

        if ($request->expectsJson()) {
            return response()->json([
                'profiles' => $profiles,
            ], 200);
        }

        return view('admin-views.trade-buyers.index', compact('profiles'));
    }

    public function show(Request $request, $id)
    {
        if ($deny = $this->checkAdminPermission($request)) {
            return $deny;
        }

        $profile = TradeBuyerProfile::with(['user', 'approver', 'auditLogs.actor'])->findOrFail($id);

        if ($request->expectsJson()) {
            return response()->json([
                'profile' => $profile,
            ], 200);
        }

        return view('admin-views.trade-buyers.show', compact('profile'));
    }

    /**
     * Atomically transition a trade buyer's review status.
     * Enforces explicit valid transitions and prevents race conditions with lockForUpdate().
     */
    public function transition(Request $request, $id)
    {
        if ($deny = $this->checkAdminPermission($request)) {
            return $deny;
        }

        $data = $request->validate([
            'status' => 'required|in:under_review,approved,rejected,revoked',
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:1000',
        ]);

        $targetStatus = $data['status'];
        $notes = $data['notes'] ?? $data['reason'] ?? null;
        $adminId = auth('admin')->id();

        $result = DB::transaction(function () use ($id, $targetStatus, $notes, $adminId, $request) {
            $profile = TradeBuyerProfile::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                return ['status' => 404, 'payload' => ['message' => 'Trade buyer profile not found.']];
            }

            $currentStatus = $profile->status;

            if ($currentStatus === $targetStatus) {
                return [
                    'status' => 422,
                    'payload' => [
                        'message' => "Profile is already {$targetStatus}.",
                        'code' => 'redundant_transition',
                    ],
                ];
            }

            if (! TradeBuyerProfile::isValidTransition($currentStatus, $targetStatus)) {
                return [
                    'status' => 422,
                    'payload' => [
                        'message' => "Invalid status transition from {$currentStatus} to {$targetStatus}.",
                        'code' => 'invalid_status_transition',
                        'current_status' => $currentStatus,
                        'target_status' => $targetStatus,
                    ],
                ];
            }

            $updateData = [
                'status' => $targetStatus,
                'updated_at' => now(),
            ];

            if ($targetStatus === TradeBuyerProfile::STATUS_APPROVED) {
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = $adminId;
                $updateData['revocation_reason'] = null;
            } elseif ($targetStatus === TradeBuyerProfile::STATUS_REJECTED) {
                $updateData['rejection_reason'] = $notes;
            } elseif ($targetStatus === TradeBuyerProfile::STATUS_REVOKED) {
                $updateData['revocation_reason'] = $notes;
                $updateData['revoked_at'] = now();
            }

            $profile->update($updateData);

            TradeBuyerAuditLog::create([
                'trade_buyer_profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'action' => $targetStatus,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'notes' => $notes,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return [
                'status' => 200,
                'payload' => [
                    'message' => "Trade buyer status transitioned to {$targetStatus}.",
                    'profile' => [
                        'id' => $profile->id,
                        'status' => $profile->status,
                        'approved_at' => $profile->approved_at,
                        'rejection_reason' => $profile->rejection_reason,
                        'revocation_reason' => $profile->revocation_reason,
                    ],
                ],
            ];
        }, 3);

        if ($request->expectsJson()) {
            return response()->json($result['payload'], $result['status']);
        }

        if ($result['status'] === 200) {
            Toastr::success($result['payload']['message']);
        } else {
            Toastr::error($result['payload']['message']);
        }

        return back();
    }
}
