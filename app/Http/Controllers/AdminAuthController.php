<?php

namespace App\Http\Controllers;

    use App\Http\Controllers\Concerns\VerifiesHuman;
    use App\Models\Admin;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\App;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Session;

/**
 * Dedicated admin login.
 *
 * Deliberately separate from LoginController: that controller resolves four
 * roles out of one settings-driven map and login_attemp() only understands
 * 'admin' and 'vendor'. Giving admin its own controller means the most
 * privileged principal is not coupled to the store/vendor role switch.
 */
class AdminAuthController extends Controller
{
    use VerifiesHuman;

    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            // Only a completed login counts. A session that exists but has not
            // passed the second factor is discarded rather than redirected into
            // the panel, where the admin-mfa middleware would bounce it anyway.
            if (app(\App\Services\AdminMfaService::class)->isVerifiedFor(Auth::guard('admin')->user())) {
                return redirect()->route('admin.dashboard');
            }

            Auth::guard('admin')->logout();
        }

        [$lang, $direction] = $this->localeAndDirection();

        $locale = session()?->get('local') ?? $lang;
        $siteDirection = session()?->get('site_direction') ?? $direction;
        App::setLocale($locale);

        return view('auth.login', [
            'role' => 'admin',
            'site_direction' => $siteDirection,
            'locale' => $locale,
            'email' => null,
            'password' => null,
            'postRoute' => route('admin.login.submit'),
            'mfa_required' => session('mfa_required', false),
            'mfa_mode' => session('mfa_mode', null),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$this->passesHumanCheck($request)) {
            return back()->withInput($request->only('email', 'remember'));
        }

        $admin = Admin::where('email', $request->email)->where('role_id', 1)->first();

        if (!$admin || !Auth::guard('admin')->attempt([
            'email' => $request->email,
            'password' => $request->password,
        ], false)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Credentials does not match.']);
        }

        // Password accepted. begin() creates the MFA pending marker and
        // destroys the guard session. We then redirect back to this same login
        // URL so the UI can slide into the 2FA panel instead of loading a new page.
        $mode = app(\App\Services\AdminMfaService::class)
            ->begin($request, $admin, $request->boolean('remember'));

        return redirect()->route('admin.login')
            ->with('mfa_required', true)
            ->with('mfa_mode', $mode);
    }

}
