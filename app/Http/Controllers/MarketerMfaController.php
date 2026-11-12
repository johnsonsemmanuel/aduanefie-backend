<?php

namespace App\Http\Controllers;

use App\Services\AdminMfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second-factor challenge and enrolment for the marketer portal.
 *
 * Same rules and same session keys as the admin panel, but it finishes by
 * sending the marketer to /marketer instead of /admin. The admin MFA routes are
 * 404 on the partners host by design, so the portal cannot simply borrow them;
 * duplicating the *rules* would risk drift, so this controller holds none. It
 * only maps the shared AdminMfaService onto marketer routes.
 */
class MarketerMfaController extends Controller
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function showChallenge(Request $request)
    {
        $pending = $this->mfa->pending($request);

        if (!$pending) {
            return redirect()->route('marketer.login');
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('marketer.mfa.setup');
        }

        return view('auth.mfa-challenge', [
            'email' => $this->mfa->pendingAdmin($request)?->email,
            'mfaRoutes' => $this->routeMap(),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin || !$admin->isMarketer()) {
            $this->mfa->clear($request);
            Auth::guard('admin')->logout();

            return redirect()->route('marketer.login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('marketer.mfa.setup');
        }

        if (!$this->mfa->verifyCode($admin, $request->input('code'))) {
            return back()->withErrors([
                'code' => 'That code did not match. Use the current code from your authenticator app, or a recovery code.',
            ]);
        }

        $this->mfa->complete($request, $admin, false);

        return redirect()->route('marketer.dashboard');
    }

    public function showSetup(Request $request)
    {
        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin || !$admin->isMarketer()) {
            $this->mfa->clear($request);
            Auth::guard('admin')->logout();

            return redirect()->route('marketer.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('marketer.mfa.challenge');
        }

        return view('auth.mfa-setup', [
            'email' => $admin->email,
            'secret' => $this->mfa->formattedSecret($admin),
            'rawSecret' => $admin->two_factor_secret,
            'provisioningUri' => $this->mfa->provisioningUri($admin),
            'mfaRoutes' => $this->routeMap(),
            'panelLabel' => 'marketer portal',
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin || !$admin->isMarketer()) {
            $this->mfa->clear($request);
            Auth::guard('admin')->logout();

            return redirect()->route('marketer.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('marketer.mfa.challenge');
        }

        $codes = $this->mfa->confirmSetup($admin, $request->input('code'));

        if ($codes === null) {
            return back()->withErrors([
                'code' => 'That code did not match. Check the clock on your device and try the code currently shown.',
            ]);
        }

        $this->mfa->complete($request, $admin, false);

        // Shown exactly once. pull() means a refresh cannot retrieve them again.
        $request->session()->put('marketer_mfa_new_recovery_codes', $codes);

        return redirect()->route('marketer.mfa.recovery-codes');
    }

    public function recoveryCodes(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || !$admin->isMarketer() || !$this->mfa->isVerifiedFor($admin)) {
            return redirect()->route('marketer.login');
        }

        $codes = $request->session()->pull('marketer_mfa_new_recovery_codes');

        if (empty($codes)) {
            return redirect()->route('marketer.dashboard');
        }

        return view('auth.mfa-recovery-codes', [
            'codes' => $codes,
            'mfaRoutes' => $this->routeMap(),
        ]);
    }

    /** @return array<string, string> */
    private function routeMap(): array
    {
        return [
            'verify' => route('marketer.mfa.verify'),
            'login' => route('marketer.login'),
            'setup' => route('marketer.mfa.setup'),
            'setup_submit' => route('marketer.mfa.setup.submit'),
            'challenge' => route('marketer.mfa.challenge'),
            'dashboard' => route('marketer.dashboard'),
        ];
    }
}
