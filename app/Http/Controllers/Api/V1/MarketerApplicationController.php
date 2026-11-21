<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\RegionalTour;
use App\Services\Markets\MarketProfileService;
use App\Traits\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class MarketerApplicationController extends Controller
{
    public function __construct(private MarketProfileService $markets)
    {
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('marketer_applications')) {
            return response()->json([
                'errors' => [['code' => 'unavailable', 'message' => 'Applications are not ready yet.']],
            ], 503);
        }

        // Resolve the applicant's market explicitly. An unsupported country is
        // rejected instead of silently validating against Ghana's regions.
        $country = $this->markets->normalizeCountry(
            $request->input('country_code') ?: $request->input('country')
        );
        if (! $country) {
            return response()->json([
                'errors' => [[
                    'code' => 'unsupported_country',
                    'message' => 'We are not accepting marketer applications in your country yet.',
                ]],
            ], 403);
        }
        $profile = $this->markets->find($country);

        $regionRule = 'required|string|max:80';
        if ($profile['address']['location_dataset'] === 'ghana') {
            $regionRule = 'required|string|in:' . implode(',', RegionalTour::GHANA_REGIONS);
        }

        $networks = array_map('strtolower', (array) $profile['payouts']['networks']);
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|min:9|max:30',
            'region' => $regionRule,
            'district' => 'nullable|string|max:120',
            'momo_network' => ['nullable', 'string', 'max:50', function ($attribute, $value, $fail) use ($networks) {
                if (! in_array(strtolower((string) $value), $networks, true)) {
                    $fail('Select a payout network available in your country.');
                }
            }],
            'experience_notes' => 'nullable|string|max:1500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->input('email')));
        if (Admin::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'errors' => [['code' => 'email', 'message' => 'An account with this email already exists on the portal.']],
            ], 409);
        }

        $existing = MarketerApplication::whereRaw('LOWER(email) = ?', [$email])
            ->where('status', MarketerApplication::STATUS_PENDING)
            ->first();
        if ($existing) {
            return response()->json([
                'data' => $existing,
                'message' => 'Your application is already under review. Our team will reach out soon.',
            ], 200);
        }

        $application = MarketerApplication::create([
            'full_name' => trim($request->input('full_name')),
            'email' => $email,
            'phone' => trim($request->input('phone')),
            'country_code' => $country,
            'region' => trim($request->input('region')),
            'district' => $request->input('district') ? trim($request->input('district')) : null,
            'momo_network' => $request->input('momo_network') ? trim($request->input('momo_network')) : null,
            'experience_notes' => $request->input('experience_notes') ? trim($request->input('experience_notes')) : null,
            'status' => MarketerApplication::STATUS_PENDING,
        ]);

        try {
            SmsGateway::send(
                $application->phone,
                "Aduanefie Partners: Hi {$application->full_name}, we received your Regional Marketer application for {$application->region}. We will review and notify you via SMS/email."
            );
        } catch (\Throwable $e) {
            // SMS must not block submission
        }

        return response()->json([
            'data' => $application,
            'message' => 'Application submitted! Our operations team will review and send your portal credentials once approved.',
        ], 201);
    }
}
