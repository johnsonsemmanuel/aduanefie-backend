<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\MarketerApplicationReceived;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\MarketerPhoneVerification;
use App\Models\RegionalTour;
use App\Models\Setting;
use App\Traits\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

        // The name and the payout number come from the verification proof, not
        // from editable form fields. A manually typed name is only accepted
        // once three failed verifications have been recorded for that number.
        $proof = $this->decodeVerificationToken($request->input('verification_token'));
        $verification = $proof ? MarketerPhoneVerification::find($proof['id'] ?? null) : null;
        if (! $proof || ! $verification || $verification->phone !== ($proof['phone'] ?? null)) {
            return response()->json([
                'errors' => [['code' => 'verification', 'message' => 'Verify your mobile money number before applying.']],
            ], 422);
        }

        $manual = ($proof['kind'] ?? '') === 'manual';
        if ($manual && ($verification->attempts < MarketerPhoneVerification::MAX_ATTEMPTS || $verification->isVerified())) {
            return response()->json([
                'errors' => [['code' => 'verification', 'message' => 'Manual name entry is not available yet.']],
            ], 422);
        }
        if (! $manual && ! $verification->isVerified()) {
            return response()->json([
                'errors' => [['code' => 'verification', 'message' => 'This number has not been verified yet.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => $manual ? 'required|string|max:150' : 'nullable|string|max:150',
            'email' => 'required|email|max:150',
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
            // A duplicate submission must not become a PII lookup endpoint.
            // Return only the state the applicant needs to know, never the
            // stored phone, notes or full record.
            return response()->json([
                'data' => [
                    'status' => $existing->status,
                    'submitted_at' => optional($existing->created_at)->toISOString(),
                ],
                'message' => 'Your application is already under review. Our team will reach out soon.',
            ], 200);
        }

        // The verified number is the one stored on the application. Approval
        // copies it to the admin account, which is what the payout flow uses.
        $fullName = $manual
            ? trim((string) $request->input('full_name'))
            : (string) $verification->verified_name;
        $network = $manual
            ? (trim((string) $request->input('momo_network')) ?: (string) $verification->network)
            : (string) $verification->network;

        $application = MarketerApplication::create([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $verification->phone,
            'phone_verified' => ! $manual,
            'phone_verification_id' => $verification->id,
            'region' => trim($request->input('region')),
            'district' => $request->input('district') ? trim($request->input('district')) : null,
            'momo_network' => $network ?: null,
            'experience_notes' => $request->input('experience_notes') ? trim($request->input('experience_notes')) : null,
            'status' => MarketerApplication::STATUS_PENDING,
        ]);

        $this->sendAcknowledgement($application);

        return response()->json([
            'data' => $application,
            'message' => 'Thank you for registering. Your application is being worked on and we will contact you soon.',
        ], 201);
    }

    /**
     * Thank the applicant on both channels. Delivery failures are logged but
     * must never turn a successful registration into an error response.
     */
    private function sendAcknowledgement(MarketerApplication $application): void
    {
        $name = $application->full_name;
        $thanks = "Thank you for registering as an Aduanefie Regional Marketer, {$name}. "
            . "Your application is being worked on. Our team will contact you on this number with an update.";

        try {
            SmsGateway::send($application->phone, $thanks);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            Mail::to($application->email, $name)
                ->send(new MarketerApplicationReceived($application));
        } catch (\Throwable $e) {
            report($e);
        }
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

        $network = strtoupper(trim((string) $request->input('momo_network', 'MTN')));
        $bankCode = trim((string) $request->input('bank_code', ''));
        if ($bankCode === '') {
            $bankCode = match (true) {
                str_contains($network, 'TELECEL') || str_contains($network, 'VOD') => 'VOD',
                str_contains($network, 'AIRTEL') || str_contains($network, 'TIGO') || $network === 'ATL' => 'ATL',
                default => 'MTN',
            };
        }

        $verification = MarketerPhoneVerification::firstOrCreate(['phone' => $rawNumber]);

        try {
            $response = Http::withToken($secretKey)
                ->timeout(10)
                ->get('https://api.paystack.co/bank/resolve', [
                    'account_number' => $rawNumber,
                    'bank_code' => $bankCode,
                ]);

            $payload = $response->json();
            if ($response->successful() && ! empty($payload['status']) && ! empty($payload['data']['account_name'])) {
                $accountName = trim((string) $payload['data']['account_name']);
                $verification->update([
                    'verified_name' => $accountName,
                    'network' => $network,
                    'verified_at' => now(),
                ]);

                return response()->json([
                    'verified' => true,
                    'account_name' => $accountName,
                    'account_number' => $payload['data']['account_number'] ?? $rawNumber,
                    'bank_code' => $bankCode,
                    'network' => $network,
                    'attempts' => $verification->attempts,
                    'manual_allowed' => false,
                    'token' => $this->signVerificationToken($verification, 'verified'),
                ], 200);
            }

            $this->recordFailedAttempt($verification);

            return response()->json([
                'verified' => false,
                'message' => $payload['message'] ?? 'Could not verify this number right now.',
                'attempts' => $verification->attempts,
                'manual_allowed' => $verification->attempts >= MarketerPhoneVerification::MAX_ATTEMPTS,
                'token' => $verification->attempts >= MarketerPhoneVerification::MAX_ATTEMPTS
                    ? $this->signVerificationToken($verification, 'manual')
                    : null,
            ], 422);
        } catch (\Throwable $e) {
            $this->recordFailedAttempt($verification);

            return response()->json([
                'verified' => false,
                'message' => 'Unable to reach the verification service right now.',
                'attempts' => $verification->attempts,
                'manual_allowed' => $verification->attempts >= MarketerPhoneVerification::MAX_ATTEMPTS,
                'token' => $verification->attempts >= MarketerPhoneVerification::MAX_ATTEMPTS
                    ? $this->signVerificationToken($verification, 'manual')
                    : null,
            ], 502);
        }
    }

    private function recordFailedAttempt(MarketerPhoneVerification $verification): void
    {
        $verification->update([
            'attempts' => min(
                MarketerPhoneVerification::MAX_ATTEMPTS,
                (int) $verification->attempts + 1
            ),
        ]);
    }

    /**
     * Signed proof of what the server saw. The browser cannot change the
     * verified name or number without invalidating the signature.
     */
    private function signVerificationToken(MarketerPhoneVerification $verification, string $kind): string
    {
        $payload = json_encode([
            'id' => $verification->id,
            'phone' => $verification->phone,
            'name' => $kind === 'verified' ? $verification->verified_name : null,
            'network' => $kind === 'verified' ? $verification->network : null,
            'kind' => $kind,
        ], JSON_THROW_ON_ERROR);

        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        return $encoded . '.' . hash_hmac('sha256', $encoded, (string) config('app.key'));
    }

    private function decodeVerificationToken(?string $token): ?array
    {
        if (! $token || ! str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $encoded, (string) config('app.key'));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')) ?: '', true);

        return is_array($payload) ? $payload : null;
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
