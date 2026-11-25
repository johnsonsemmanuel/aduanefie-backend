<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\TradeBuyerAuditLog;
use App\Models\TradeBuyerProfile;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TradeAccessController extends Controller
{
    public function __construct(private FeatureFlagService $flags)
    {
    }

    /**
     * Submit or resubmit an application for cross-border trade sourcing access.
     */
    public function apply(Request $request)
    {
        if (! $this->flags->allowsNewTransactions('trade_access_applications')) {
            return response()->json([
                'message' => 'Cross-border trade buyer applications are currently closed pending operational review.',
                'code' => 'trade_applications_disabled',
            ], 403);
        }

        $user = $request->user();
        if (! $user) {
            try {
                $user = auth('api')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }
        if (! $user) {
            return response()->json([
                'message' => 'Authentication is required to apply for trade access.',
                'code' => 'trade_auth_required',
            ], 401);
        }

        // Normalize alias input fields for individual traders and flexible naming
        $country = $request->input('country_of_operation')
            ?? $request->input('country_of_incorporation')
            ?? $request->input('country');

        $destinations = $request->input('intended_destinations')
            ?? $request->input('destination_countries');

        $commodities = $request->input('target_commodities')
            ?? $request->input('sourcing_categories');

        $volume = $request->input('estimated_volume')
            ?? $request->input('annual_volume_estimate');

        $taxId = $request->input('tax_or_reg_number')
            ?? $request->input('tax_id');

        $payload = array_merge($request->all(), array_filter([
            'country_of_operation' => $country,
            'intended_destinations' => $destinations,
            'target_commodities' => $commodities,
            'estimated_volume' => $volume,
            'tax_or_reg_number' => $taxId,
        ], fn ($val) => ! is_null($val)));

        $validator = Validator::make($payload, [
            'applicant_name' => 'required|string|max:120',
            'organization_name' => 'nullable|string|max:150', // Optional for individual traders
            'country_of_operation' => 'required|string|max:80',
            'contact_phone' => 'required|string|max:40',
            'contact_email' => 'nullable|email|max:150',
            'business_type' => 'nullable|string|max:80',
            'target_commodities' => 'nullable|array',
            'target_commodities.*' => 'string|max:80',
            'intended_destinations' => 'nullable|array',
            'intended_destinations.*' => 'string|max:80',
            'estimated_volume' => 'nullable|string|max:100',
            'tax_or_reg_number' => 'nullable|string|max:80',
            'sourcing_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $data = $validator->validated();

        try {
            return DB::transaction(function () use ($user, $data, $request) {
                $existing = TradeBuyerProfile::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->isPendingOrReview()) {
                        return response()->json([
                            'message' => 'You already have an active trade access application under review.',
                            'code' => 'application_already_in_progress',
                            'status' => $existing->status,
                            'message_detail' => $existing->getStatusMessage(),
                        ], 409);
                    }

                    if ($existing->isApproved()) {
                        return response()->json([
                            'message' => 'Your cross-border trade access is already approved.',
                            'code' => 'already_approved',
                            'status' => $existing->status,
                        ], 409);
                    }

                    if ($existing->isRevoked()) {
                        return response()->json([
                            'message' => 'Your trade access was previously revoked. Please contact trade operations.',
                            'code' => 'trade_access_revoked',
                            'status' => $existing->status,
                        ], 403);
                    }

                    if ($existing->isRejected()) {
                        // Allowed resubmission: update profile with fresh details and return to pending
                        $fromStatus = $existing->status;
                        $existing->update([
                            'applicant_name' => trim($data['applicant_name']),
                            'organization_name' => isset($data['organization_name']) ? trim($data['organization_name']) : null,
                            'country_of_operation' => trim($data['country_of_operation']),
                            'contact_phone' => trim($data['contact_phone']),
                            'contact_email' => $data['contact_email'] ?? $user->email,
                            'business_type' => $data['business_type'] ?? null,
                            'target_commodities' => $data['target_commodities'] ?? null,
                            'intended_destinations' => $data['intended_destinations'] ?? null,
                            'estimated_volume' => $data['estimated_volume'] ?? null,
                            'tax_or_reg_number' => $data['tax_or_reg_number'] ?? null,
                            'sourcing_notes' => $data['sourcing_notes'] ?? null,
                            'status' => TradeBuyerProfile::STATUS_PENDING,
                            'rejection_reason' => null,
                            'updated_at' => now(),
                        ]);

                        TradeBuyerAuditLog::create([
                            'trade_buyer_profile_id' => $existing->id,
                            'user_id' => $user->id,
                            'action' => 'resubmitted',
                            'from_status' => $fromStatus,
                            'to_status' => TradeBuyerProfile::STATUS_PENDING,
                            'actor_type' => 'user',
                            'actor_id' => $user->id,
                            'notes' => 'Applicant resubmitted revised trade access application.',
                            'ip_address' => $request->ip(),
                            'created_at' => now(),
                        ]);

                        return response()->json([
                            'message' => 'Trade access application resubmitted successfully.',
                            'profile' => [
                                'id' => $existing->id,
                                'status' => $existing->status,
                                'status_message' => $existing->getStatusMessage(),
                            ],
                        ], 200);
                    }
                }

                // Fresh submission
                $profile = TradeBuyerProfile::create([
                    'user_id' => $user->id,
                    'applicant_name' => trim($data['applicant_name']),
                    'organization_name' => isset($data['organization_name']) ? trim($data['organization_name']) : null,
                    'country_of_operation' => trim($data['country_of_operation']),
                    'contact_phone' => trim($data['contact_phone']),
                    'contact_email' => $data['contact_email'] ?? $user->email,
                    'business_type' => $data['business_type'] ?? null,
                    'target_commodities' => $data['target_commodities'] ?? null,
                    'intended_destinations' => $data['intended_destinations'] ?? null,
                    'estimated_volume' => $data['estimated_volume'] ?? null,
                    'tax_or_reg_number' => $data['tax_or_reg_number'] ?? null,
                    'sourcing_notes' => $data['sourcing_notes'] ?? null,
                    'status' => TradeBuyerProfile::STATUS_PENDING,
                ]);

                TradeBuyerAuditLog::create([
                    'trade_buyer_profile_id' => $profile->id,
                    'user_id' => $user->id,
                    'action' => 'submitted',
                    'from_status' => null,
                    'to_status' => TradeBuyerProfile::STATUS_PENDING,
                    'actor_type' => 'user',
                    'actor_id' => $user->id,
                    'notes' => 'Applicant submitted trade access application.',
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Trade access application submitted successfully.',
                    'profile' => [
                        'id' => $profile->id,
                        'status' => $profile->status,
                        'status_message' => $profile->getStatusMessage(),
                    ],
                ], 201);
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = (int) ($e->errorInfo[1] ?? 0);
            $errorMessage = $e->getMessage();

            // 1. Identify specific duplicate-key conflict on trade_buyer_profiles.user_id
            // Specifically distinguish duplicate entry on user_id from other SQLSTATE 23000
            // errors (e.g. foreign key constraint violations 1452/1216 or not-null 1048)
            $isDuplicateUser = false;
            if ($errorCode === 1062) { // MySQL ER_DUP_ENTRY
                $isDuplicateUser = str_contains($errorMessage, 'trade_buyer_profiles_user_id_unique')
                    || str_contains($errorMessage, 'user_id');
            } elseif ($errorCode === 19 || str_contains($errorMessage, 'UNIQUE constraint failed')) { // SQLite
                $isDuplicateUser = str_contains($errorMessage, 'trade_buyer_profiles.user_id')
                    || str_contains($errorMessage, 'user_id');
            }

            if ($isDuplicateUser) {
                // Return 409 ONLY when profile for the authenticated user is confirmed to exist
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $existing = TradeBuyerProfile::where('user_id', $user->id)->first();
                    if ($existing) {
                        return response()->json([
                            'message' => 'You already have an active trade access application under review.',
                            'code' => 'application_already_in_progress',
                            'status' => $existing->status,
                            'message_detail' => $existing->getStatusMessage(),
                        ], 409);
                    }
                    usleep(15000); // 15ms
                }
            }

            // 2. Identify bounded deadlock (1213 / 40001) or lock wait timeout (1205 / HY000)
            $isDeadlockOrTimeout = in_array($errorCode, [1213, 1205], true)
                || in_array(($e->errorInfo[0] ?? ''), ['40001'], true);

            if ($isDeadlockOrTimeout) {
                // Check if a competing winning transaction committed a profile for this user
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $existing = TradeBuyerProfile::where('user_id', $user->id)->first();
                    if ($existing) {
                        return response()->json([
                            'message' => 'You already have an active trade access application under review.',
                            'code' => 'application_already_in_progress',
                            'status' => $existing->status,
                            'message_detail' => $existing->getStatusMessage(),
                        ], 409);
                    }
                    usleep(15000); // 15ms
                }

                // If retries are exhausted without an existing profile, return retryable failure rather than a false duplicate
                return response()->json([
                    'message' => 'Application processing is temporarily busy due to database contention. Please retry your submission.',
                    'code' => 'trade_application_temporarily_busy',
                ], 503, ['Retry-After' => '1']);
            }

            // 3. All other database exceptions (unrelated 23000 foreign key/null violations, syntax, connection) are strictly preserved
            throw $e;
        }
    }

    /**
     * Check applicant's own trade access application status.
     * Enforces ownership isolation: only the authenticated user's own status is returned.
     */
    public function status(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            try {
                $user = auth('api')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }
        if (! $user) {
            return response()->json([
                'message' => 'Authentication is required to check trade status.',
                'code' => 'trade_auth_required',
            ], 401);
        }

        if (! $this->flags->allowsHistoricalAccess('trade_access_applications')) {
            return response()->json([
                'applied' => false,
                'status' => null,
                'message' => 'Trade access inquiry is currently disabled.',
            ], 200);
        }

        $profile = TradeBuyerProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return response()->json([
                'applied' => false,
                'status' => null,
                'message' => 'No cross-border trade access application found.',
            ], 200);
        }

        return response()->json([
            'applied' => true,
            'status' => $profile->status,
            'applicant_name' => $profile->applicant_name,
            'organization_name' => $profile->organization_name,
            'country_of_operation' => $profile->country_of_operation,
            'target_commodities' => $profile->target_commodities,
            'intended_destinations' => $profile->intended_destinations,
            'submitted_at' => $profile->created_at,
            'approved_at' => $profile->approved_at,
            'rejection_reason' => $profile->isRejected() ? $profile->rejection_reason : null,
            'revocation_reason' => $profile->isRevoked() ? $profile->revocation_reason : null,
            'message' => $profile->getStatusMessage(),
        ], 200);
    }
}
