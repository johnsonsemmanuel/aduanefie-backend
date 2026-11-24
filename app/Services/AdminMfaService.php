<?php

namespace App\Services;

use App\CentralLogics\Totp;
use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The single place that decides whether a principal has passed a second factor.
 *
 * Guard-agnostic: the same rules apply to the admin guard, the vendor guard,
 * and any other guard whose users have two_factor_* columns. Session keys are
 * scoped per guard ({guard}_mfa_pending, {guard}_mfa_verified) so an admin
 * MFA session cannot be reused by a vendor session and vice versa.
 *
 * Everything else (login controllers, panel middleware, challenge controllers)
 * asks this class. Keeping the rules in one object is deliberate: the failure
 * this guards against is a second login path that forgets to ask.
 */
class AdminMfaService
{
    public const SESSION_PENDING = 'admin_mfa_pending';
    public const SESSION_VERIFIED = 'admin_mfa_verified';

    public const MODE_SETUP = 'setup';
    public const MODE_CHALLENGE = 'challenge';

    /** Map of guard name to the Eloquent model class that owns two_factor_* columns. */
    private const GUARD_MODELS = [
        'admin' => Admin::class,
        'vendor' => Vendor::class,
    ];

    private function pendingKey(string $guard): string
    {
        return $guard . '_mfa_pending';
    }

    private function verifiedKey(string $guard): string
    {
        return $guard . '_mfa_verified';
    }

    /**
     * Resolve the pending user for a guard, using the guard's model class.
     */
    private function modelForGuard(string $guard): string
    {
        return self::GUARD_MODELS[$guard] ?? Admin::class;
    }

    /**
     * Called immediately after a password has been verified.
     *
     * The password-only session is destroyed before this returns, so there is
     * no window in which a guard-authenticated but MFA-unverified user exists.
     * Returns the mode the caller should redirect to.
     */
    public function begin(Request $request, $user, bool $remember = false, string $guard = 'admin'): string
    {
        $mode = $user->hasConfirmedTwoFactor() ? self::MODE_CHALLENGE : self::MODE_SETUP;

        // Undo the session created by attempt()/loginUsingId(). logout() also
        // unqueues the recaller cookie, so "remember me" cannot resurrect the
        // half-authenticated session on the next request.
        Auth::guard($guard)->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        if ($mode === self::MODE_SETUP) {
            // A fresh secret per setup attempt. An abandoned attempt must not
            // leave a usable secret behind.
            $user->forceFill([
                'two_factor_secret' => Totp::generateSecret(),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
        }

        $request->session()->forget($this->verifiedKey($guard));
        $request->session()->put($this->pendingKey($guard), [
            'id' => $user->id,
            'mode' => $mode,
            'remember' => $remember,
            'issued_at' => now()->getTimestamp(),
        ]);

        return $mode;
    }

    /**
     * The pending marker, if it is still valid. Expired markers are removed.
     *
     * @param  string  $guard  Authentication guard (defaults to 'admin')
     * @return array{id:int,mode:string,remember:bool,issued_at:int}|null
     */
    public function pending(Request $request, string $guard = 'admin'): ?array
    {
        $pending = $request->session()->get($this->pendingKey($guard));

        if (!is_array($pending) || empty($pending['id']) || empty($pending['issued_at'])) {
            return null;
        }

        if (now()->getTimestamp() - (int) $pending['issued_at'] > (int) config('admin_mfa.pending_ttl')) {
            $request->session()->forget($this->pendingKey($guard));

            return null;
        }

        return $pending;
    }

    /**
     * The pending user for the given guard, if the pending marker is valid.
     *
     * @param  string  $guard  Authentication guard (defaults to 'admin')
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function pendingUser(Request $request, string $guard = 'admin')
    {
        $pending = $this->pending($request, $guard);

        return $pending ? app($this->modelForGuard($guard))->find($pending['id']) : null;
    }

    /**
     * Backward-compatible alias for pendingUser() with the admin guard.
     *
     * @return \App\Models\Admin|null
     */
    public function pendingAdmin(Request $request): ?Admin
    {
        return $this->pendingUser($request, 'admin');
    }

    /**
     * Has this user completed a second factor in *this* session?
     *
     * @param  string  $guard  Authentication guard (defaults to 'admin')
     */
    public function isVerifiedFor($user, string $guard = 'admin'): bool
    {
        if (!$user || !$user->hasConfirmedTwoFactor()) {
            return false;
        }

        $verified = session()->get($this->verifiedKey($guard));

        if (!is_array($verified) || (int) ($verified['id'] ?? 0) !== (int) $user->id) {
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
    public function verifyCode($user, ?string $code): bool
    {
        return $this->verifyTotp($user, $code) || $this->consumeRecoveryCode($user, $code);
    }

    public function verifyTotp($user, ?string $code): bool
    {
        if (!$user->hasConfirmedTwoFactor()) {
            return false;
        }

        $step = Totp::verify(
            (string) $user->two_factor_secret,
            $code,
            now()->getTimestamp(),
            (int) config('admin_mfa.window')
        );

        if ($step === null) {
            return false;
        }

        // Replay guard: the same 30-second step is only valid once.
        if ($user->two_factor_last_step !== null && $step <= (int) $user->two_factor_last_step) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /**
     * Confirm enrolment with the first valid code from the new secret.
     *
     * @return string[]|null Plaintext recovery codes, or null if the code was wrong.
     */
    public function confirmSetup($user, ?string $code): ?array
    {
        if ($user->hasConfirmedTwoFactor() || empty($user->two_factor_secret)) {
            return null;
        }

        $step = Totp::verify(
            (string) $user->two_factor_secret,
            $code,
            now()->getTimestamp(),
            (int) config('admin_mfa.window')
        );

        if ($step === null) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
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
     *
     * @param  string  $guard  Authentication guard (defaults to 'admin')
     */
    public function complete(Request $request, $user, bool $remember = false, string $guard = 'admin'): void
    {
        Auth::guard($guard)->login($user, $remember);

        $request->session()->regenerate();
        $request->session()->forget($this->pendingKey($guard));
        $request->session()->put($this->verifiedKey($guard), [
            'id' => $user->id,
            'at' => now()->getTimestamp(),
        ]);

        if (method_exists($user, 'forceFill')) {
            $user->forceFill(['is_logged_in' => 1])->save();
        }
    }

    public function provisioningUri($user): string
    {
        return Totp::provisioningUri(
            (string) $user->two_factor_secret,
            (string) $user->email,
            (string) config('admin_mfa.issuer')
        );
    }

    /**
     * Format a base32 secret for manual entry: XXXX XXXX XXXX XXXX.
     */
    public function formattedSecret($user): string
    {
        return trim(chunk_split((string) $user->two_factor_secret, 4, ' '));
    }

    public function clear(Request $request, string $guard = 'admin'): void
    {
        $request->session()->forget([$this->pendingKey($guard), $this->verifiedKey($guard)]);
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

    private function consumeRecoveryCode($user, ?string $code): bool
    {
        $candidate = $this->normaliseRecoveryCode((string) $code);
        if ($candidate === '') {
            return false;
        }

        $stored = $user->two_factor_recovery_codes;
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

        $user->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();

        return true;
    }

    private function normaliseRecoveryCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code))) ?? '';
    }
}
