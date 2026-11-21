<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\RegionalTour;
use App\Models\Setting;
use App\Traits\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    /**
     * Verify a mobile-money or bank account number via Paystack's Resolve Account API
     * and return the registered account holder name.
     */
    public function verifyPayout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|min:9|max:20',
            'momo_network' => 'nullable|string|max:50',
            'bank_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $secretKey = $this->resolvePaystackSecretKey();
        if (! $secretKey) {
            return response()->json([
                'verified' => false,
                'message' => 'Paystack verification is not configured on the server.',
            ], 503);
        }

        $rawNumber = preg_replace('/\D+/', '', (string) $request->input('account_number'));
        if (str_starts_with($rawNumber, '233') && strlen($rawNumber) === 12) {
            $rawNumber = '0' . substr($rawNumber, 3);
        }

        $bankCode = trim((string) $request->input('bank_code', ''));
        if ($bankCode === '') {
            $network = strtoupper(trim((string) $request->input('momo_network', 'MTN')));
            $bankCode = match (true) {
                str_contains($network, 'TELECEL') || str_contains($network, 'VOD') => 'VOD',
                str_contains($network, 'AIRTEL') || str_contains($network, 'TIGO') || $network === 'ATL' => 'ATL',
                default => 'MTN',
            };
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(10)
                ->get('https://api.paystack.co/bank/resolve', [
                    'account_number' => $rawNumber,
                    'bank_code' => $bankCode,
                ]);

            $payload = $response->json();
            if ($response->successful() && ! empty($payload['status']) && ! empty($payload['data']['account_name'])) {
                return response()->json([
                    'verified' => true,
                    'account_name' => trim((string) $payload['data']['account_name']),
                    'account_number' => $payload['data']['account_number'] ?? $rawNumber,
                    'bank_code' => $bankCode,
                ], 200);
            }

            return response()->json([
                'verified' => false,
                'message' => $payload['message'] ?? 'Could not verify this number with Paystack.',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'verified' => false,
                'message' => 'Unable to reach Paystack verification service right now.',
            ], 502);
        }
    }

    private function resolvePaystackSecretKey(): ?string
    {
        try {
            $setting = Setting::where('settings_type', 'payment_config')
                ->where('key_name', 'paystack')
                ->first();
            if ($setting) {
                $values = $setting->mode === 'live'
                    ? (is_string($setting->live_values) ? json_decode($setting->live_values, true) : $setting->live_values)
                    : (is_string($setting->test_values) ? json_decode($setting->test_values, true) : $setting->test_values);
                if (! empty($values['secret_key'])) {
                    return (string) $values['secret_key'];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to env
        }

        $envKey = env('PAYSTACK_SECRET_KEY');
        return $envKey ? (string) $envKey : null;
    }
}
