<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\AdminMfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second-factor challenge and enrolment.
 *
 * These routes sit in front of the admin panel and are therefore *not* inside
 * the panel's own middleware group: the admin is not authenticated yet while
 * they are here. Identity comes from the short-lived pending marker that
 * AdminMfaService::begin() writes only after the password has been verified.
 */
class AdminMfaController extends Controller
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function showChallenge(Request $request)
    {
        $pending = $this->mfa->pending($request);

        if (!$pending) {
            return redirect()->route('admin.login');
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('admin.mfa.setup');
        }

        return view('auth.mfa-challenge', [
            'email' => $this->mfa->pendingAdmin($request)?->email,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin) {
            $this->mfa->clear($request);

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('admin.mfa.setup');
        }

        if (!$this->mfa->verifyCode($admin, $request->input('code'))) {
            return back()->withErrors([
                'code' => 'That code did not match. Use the current code from your authenticator app, or a recovery code.',
            ]);
        }

        $this->mfa->complete($request, $admin, (bool) ($pending['remember'] ?? false));

        return $this->dashboardRedirect();
    }

    public function showSetup(Request $request)
    {
        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin) {
            return redirect()->route('admin.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('admin.mfa.challenge');
        }

        return view('auth.mfa-setup', [
            'email' => $admin->email,
            'secret' => $this->mfa->formattedSecret($admin),
            'rawSecret' => $admin->two_factor_secret,
            'provisioningUri' => $this->mfa->provisioningUri($admin),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request);
        $admin = $this->mfa->pendingAdmin($request);

        if (!$pending || !$admin) {
            return redirect()->route('admin.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('admin.mfa.challenge');
        }

        $codes = $this->mfa->confirmSetup($admin, $request->input('code'));

        if ($codes === null) {
            return back()->withErrors([
                'code' => 'That code did not match. Check the clock on your device and try the code currently shown.',
            ]);
        }

        $this->mfa->complete($request, $admin, (bool) ($pending['remember'] ?? false));

        // Shown exactly once. pull() means a refresh cannot retrieve them again.
        $request->session()->put('admin_mfa_new_recovery_codes', $codes);

        return redirect()->route('admin.mfa.recovery-codes');
    }

    public function recoveryCodes(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || !$this->mfa->isVerifiedFor($admin)) {
            return redirect()->route('admin.login');
        }

        $codes = $request->session()->pull('admin_mfa_new_recovery_codes');

        if (empty($codes)) {
            return $this->dashboardRedirect();
        }

        return view('auth.mfa-recovery-codes', ['codes' => $codes]);
    }

    private function dashboardRedirect()
    {
        $modules = Module::Active()->get();

        if ($modules->count() > 0) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin.business-settings.business-setup');
    }
}
