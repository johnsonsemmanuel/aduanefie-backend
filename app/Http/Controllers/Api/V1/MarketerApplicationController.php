<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\RegionalTour;
use App\Traits\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class MarketerApplicationController extends Controller
{
    public function store(Request $request)
    {
        if (! Schema::hasTable('marketer_applications')) {
            return response()->json([
                'errors' => [['code' => 'unavailable', 'message' => 'Applications are not ready yet.']],
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|min:9|max:30',
            'region' => 'required|string|in:' . implode(',', RegionalTour::GHANA_REGIONS),
            'district' => 'nullable|string|max:120',
            'momo_network' => 'nullable|string|max:50',
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
