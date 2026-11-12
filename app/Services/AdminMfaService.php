<?php

namespace App\Services;

use App\CentralLogics\Totp;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The single place that decides whether an admin has passed a second factor.
 *
 * Everything else (both login controllers, the panel middleware and the
 * challenge controller) asks this class. Keeping the rules in one object is
 * deliberate: the failure this guards against is a second login path that
 * forgets to ask.
 */
class AdminMfaService
{
    public const SESSION_PENDING = 'admin_mfa_pending';
    public const SESSION_VERIFIED = 'admin_mfa_verified';

    public const MODE_SETUP = 'setup';
    public const MODE_CHALLENGE = 'challenge';

    /**
     * Called immediately after a password has been verified.
     *
     * The password-only session is destroyed before this returns, so there is
     * no window in which a guard-authenticated but MFA-unverified admin exists.
     * Returns the mode the caller should redirect to.
     */
    public function begin(Request $request, Admin $admin, bool $remember = false): string
    {
        $mode = $admin->hasConfirmedTwoFactor() ? self::MODE_CHALLENGE : self::MODE_SETUP;

        // Undo the session created by attempt()/loginUsingId(). logout() also
        // unqueues the recaller cookie, so "remember me" cannot resurrect the
        // half-authenticated session on the next request.
        Auth::guard('admin')->logout();
        $request->session()->regenerate();

        if ($mode === self::MODE_SETUP) {
            // A fresh secret per setup attempt. An abandoned attempt must not
            // leave a usable secret behind.
            $admin->forceFill([
                'two_factor_secret' => Totp::generateSecret(),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
        }

        $request->session()->forget(self::SESSION_VERIFIED);
        $request->session()->put(self::SESSION_PENDING, [
            'id' => $admin->id,
            'mode' => $mode,
            'remember' => $remember,
            'issued_at' => now()->getTimestamp(),
        ]);

        return $mode;
    }

    /**
     * The pending marker, if it is still valid. Expired markers are removed.
     *
     * @return array{id:int,mode:string,remember:bool,issued_at:int}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_PENDING);

        if (!is_array($pending) || empty($pending['id']) || empty($pending['issued_at'])) {
            return null;
        }

        if (now()->getTimestamp() - (int) $pending['issued_at'] > (int) config('admin_mfa.pending_ttl')) {
            $request->session()->forget(self::SESSION_PENDING);

            return null;
        }

        return $pending;
    }

    public function pendingAdmin(Request $request): ?Admin
    {
        $pending = $this->pending($request);

        return $pending ? Admin::find($pending['id']) : null;
    }

    /**
     * Has this admin completed a second factor in *this* session?
     */
    public function isVerifiedFor(?Admin $admin): bool
    {
        if (!$admin || !$admin->hasConfirmedTwoFactor()) {
            return false;
        }

        $verified = session()->get(self::SESSION_VERIFIED);

        if (!is_array($verified) || (int) ($verified['id'] ?? 0) !== (int) $admin->id) {
            return false;
        }

        $ttl = (int) config('admin_mfa.session_ttl');
        if ($ttl > 0 && now()->getTimestamp() - (int) ($verified['at'] ?? 0) > $ttl) {
            return false;
        }

        return true;
    }

    /**
     * Accept either a TOTP code or a one-time recovery code.
     */
    public function verifyCode(Admin $admin, ?string $code): bool
    {
        return $this->verifyTotp($admin, $code) || $this->consumeRecoveryCode($admin, $code);
    }

    public function verifyTotp(Admin $admin, ?string $code): bool
    {
        if (!$admin->hasConfirmedTwoFactor()) {
            return false;
        }

        $step = Totp::verify(
            (string) $admin->two_factor_secret,
            $code,
            now()->getTimestamp(),
            (int) config('admin_mfa.window')
        );

        if ($step === null) {
            return false;
        }

        // Replay guard: the same 30-second step is only valid once.
        if ($admin->two_factor_last_step !== null && $step <= (int) $admin->two_factor_last_step) {
            return false;
        }

        $admin->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /**
     * Confirm enrolment with the first valid code from the new secret.
     *
     * @return string[]|null Plaintext recovery codes, or null if the code was wrong.
     */
    public function confirmSetup(Admin $admin, ?string $code): ?array
    {
        if ($admin->hasConfirmedTwoFactor() || empty($admin->two_factor_secret)) {
            return null;
        }

        $step = Totp::verify(
            (string) $admin->two_factor_secret,
            $code,
            now()->getTimestamp(),
            (int) config('admin_mfa.window')
        );

        if ($step === null) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_recovery_codes' => array_map(
                fn (string $code) => hash('sha256', $this->normaliseRecoveryCode($code)),
                $codes
            ),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();

        return $codes;
    }

    /**
     * Establish the real session. Only call this after verifyCode()/confirmSetup().
     */
    public function complete(Request $request, Admin $admin, bool $remember = false): void
    {
        Auth::guard('admin')->login($admin, $remember);

        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_PENDING);
        $request->session()->put(self::SESSION_VERIFIED, [
            'id' => $admin->id,
            'at' => now()->getTimestamp(),
        ]);

        $admin->forceFill(['is_logged_in' => 1])->save();
    }

    public function provisioningUri(Admin $admin): string
    {
        return Totp::provisioningUri(
            (string) $admin->two_factor_secret,
            (string) $admin->email,
            (string) config('admin_mfa.issuer')
        );
    }

    /**
     * Format a base32 secret for manual entry: XXXX XXXX XXXX XXXX.
     */
    public function formattedSecret(Admin $admin): string
    {
        return trim(chunk_split((string) $admin->two_factor_secret, 4, ' '));
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([self::SESSION_PENDING, self::SESSION_VERIFIED]);
    }

    /**
     * @return string[]
     */
    public function generateRecoveryCodes(): array
    {
        $count = max(1, (int) config('admin_mfa.recovery_codes'));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I, O, 0, 1
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }

        return $codes;
    }

    private function consumeRecoveryCode(Admin $admin, ?string $code): bool
    {
        $candidate = $this->normaliseRecoveryCode((string) $code);
        if ($candidate === '') {
            return false;
        }

        $stored = $admin->two_factor_recovery_codes;
        if (!is_array($stored) || $stored === []) {
            return false;
        }

        $candidateHash = hash('sha256', $candidate);
        $remaining = [];
        $matched = false;

        foreach ($stored as $hash) {
            if (!$matched && is_string($hash) && hash_equals($hash, $candidateHash)) {
                $matched = true;
                continue;
            }
            $remaining[] = $hash;
        }

        if (!$matched) {
            return false;
        }

        $admin->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();

        return true;
    }

    private function normaliseRecoveryCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code))) ?? '';
    }
}
