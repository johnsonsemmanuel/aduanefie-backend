<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\AdminMfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second-factor challenge and enrolment for the vendor panel on
 * admin.aduanefie.com/store-login.
 *
 * Same rules as the admin panel, but it finishes by sending the vendor to the
 * vendor dashboard instead of /admin. Duplicating the rules would risk drift,
 * so this controller only maps the shared AdminMfaService onto vendor routes
 * and adds vendor-guard awareness (pendingUser with guard 'vendor', complete
 * with guard 'vendor').
 */
class VendorMfaController extends Controller
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function showChallenge(Request $request)
    {
        $pending = $this->mfa->pending($request, 'vendor');

        if (!$pending) {
            return redirect()->route('store.login');
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('vendor.mfa.setup');
        }

        return view('auth.mfa-challenge', [
            'email' => $this->mfa->pendingUser($request, 'vendor')?->email,
            'mfaRoutes' => $this->routeMap(),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request, 'vendor');
        $vendor = $this->mfa->pendingUser($request, 'vendor');

        if (!$pending || !$vendor || !($vendor instanceof Vendor)) {
            $this->mfa->clear($request, 'vendor');
            Auth::guard('vendor')->logout();

            return redirect()->route('store.login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

        if ($pending['mode'] === AdminMfaService::MODE_SETUP) {
            return redirect()->route('vendor.mfa.setup');
        }

        if (!$this->mfa->verifyCode($vendor, $request->input('code'))) {
            return back()->withErrors([
                'code' => 'That code did not match. Use the current code from your authenticator app, or a recovery code.',
            ]);
        }

        $this->mfa->complete($request, $vendor, false, 'vendor');

        return redirect()->route('vendor.dashboard');
    }

    public function showSetup(Request $request)
    {
        $pending = $this->mfa->pending($request, 'vendor');
        $vendor = $this->mfa->pendingUser($request, 'vendor');

        if (!$pending || !$vendor || !($vendor instanceof Vendor)) {
            $this->mfa->clear($request, 'vendor');
            Auth::guard('vendor')->logout();

            return redirect()->route('store.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('vendor.mfa.challenge');
        }

        return view('auth.mfa-setup', [
            'email' => $vendor->email,
            'secret' => $this->mfa->formattedSecret($vendor),
            'rawSecret' => $vendor->two_factor_secret,
            'provisioningUri' => $this->mfa->provisioningUri($vendor),
            'mfaRoutes' => $this->routeMap(),
            'panelLabel' => 'vendor panel',
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $pending = $this->mfa->pending($request, 'vendor');
        $vendor = $this->mfa->pendingUser($request, 'vendor');

        if (!$pending || !$vendor || !($vendor instanceof Vendor)) {
            $this->mfa->clear($request, 'vendor');
            Auth::guard('vendor')->logout();

            return redirect()->route('store.login');
        }

        if ($pending['mode'] !== AdminMfaService::MODE_SETUP) {
            return redirect()->route('vendor.mfa.challenge');
        }

        $codes = $this->mfa->confirmSetup($vendor, $request->input('code'));

        if ($codes === null) {
            return back()->withErrors([
                'code' => 'That code did not match. Check the clock on your device and try the code currently shown.',
            ]);
        }

        $this->mfa->complete($request, $vendor, false, 'vendor');

        // Shown exactly once. pull() means a refresh cannot retrieve them again.
        $request->session()->put('vendor_mfa_new_recovery_codes', $codes);

        return redirect()->route('vendor.mfa.recovery-codes');
    }

    public function recoveryCodes(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        if (!$vendor || !$vendor instanceof Vendor || !$this->mfa->isVerifiedFor($vendor, 'vendor')) {
            return redirect()->route('store.login');
        }

        $codes = $request->session()->pull('vendor_mfa_new_recovery_codes');

        if (empty($codes)) {
            return redirect()->route('vendor.dashboard');
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
            'verify' => route('vendor.mfa.verify'),
            'login' => route('store.login'),
            'setup' => route('vendor.mfa.setup'),
            'setup_submit' => route('vendor.mfa.setup.submit'),
            'challenge' => route('vendor.mfa.challenge'),
            'dashboard' => route('vendor.dashboard'),
        ];
    }
}
