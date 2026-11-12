<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesHuman;
use App\Models\Admin;
use App\Services\AdminMfaService;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

/**
 * Login for the remote-marketer portal on partners.aduanefie.com.
 *
 * Marketers are admins with role 5. This portal deliberately reuses the admin
 * guard and the admin second-factor service rather than introducing a second
 * credential path for the same accounts. A separate guard over the same table
 * would be a weaker door into accounts that can request money, which is exactly
 * the silent downgrade the MFA work exists to prevent.
 *
 * The role and password are checked *before* any session is created, and the
 * password alone never produces a usable session: AdminMfaService::begin()
 * destroys it and leaves only a short-lived pending marker.
 */
class MarketerAuthController extends Controller
{
    use VerifiesHuman;

    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();

            if ($admin->isMarketer() && $this->mfa->isVerifiedFor($admin)) {
                return redirect()->route('marketer.dashboard');
            }

            // Half-authenticated or not a marketer: do not carry the session
            // into this portal.
            Auth::guard('admin')->logout();
        }

        [$lang, $direction] = $this->localeAndDirection();

        $locale = session()?->get('local') ?? $lang;
        $siteDirection = session()?->get('site_direction') ?? $direction;
        App::setLocale($locale);

        $captcha = new CaptchaBuilder;
        $captcha->build();
        Session::put('six_captcha', $captcha->getPhrase());

        return view('marketer.login', [
            'custome_recaptcha' => $captcha,
            'site_direction' => $siteDirection,
            'locale' => $locale,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'password' => 'required|min:6',
        ]);

        if (!$this->passesHumanCheck($request)) {
            return back()->withInput($request->only('email'));
        }

        $admin = Admin::where('email', $request->email)
            ->where('role_id', config('marketer.role_id', 5))
            ->first();

        if (!$admin || !$admin->password || !Hash::check((string) $request->password, $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => translate('messages.Incorrect_credential,_please_try_again')]);
        }

        $mode = $this->mfa->begin($request, $admin, false);

        return redirect()->route(
            $mode === AdminMfaService::MODE_SETUP
                ? 'marketer.mfa.setup'
                : 'marketer.mfa.challenge'
        );
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketer.login');
    }
}
