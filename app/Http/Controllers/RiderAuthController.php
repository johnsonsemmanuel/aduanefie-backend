<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesHuman;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Web login for the rider portal on logistics.aduanefie.com.
 *
 * Riders authenticate by phone, not email — that is what the mobile API accepts
 * (DeliveryManLoginController@login) and the only credential they were issued.
 *
 * The two account gates below mirror the API exactly. A web session must not be
 * easier to obtain than an API token, so both paths fail closed on the same
 * conditions: application not approved, and suspended account.
 */
class RiderAuthController extends Controller
{
    use VerifiesHuman;

    public function showLogin()
    {
        if (Auth::guard('delivery_men')->check()) {
            return redirect()->route('rider.dashboard');
        }

        [$lang, $direction] = $this->localeAndDirection();

        $locale = session()?->get('local') ?? $lang;
        $siteDirection = session()?->get('site_direction') ?? $direction;
        App::setLocale($locale);

        $captcha = new CaptchaBuilder;
        $captcha->build();
        Session::put('six_captcha', $captcha->getPhrase());

        return view('rider.login', [
            'custome_recaptcha' => $captcha,
            'site_direction' => $siteDirection,
            'locale' => $locale,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'password' => 'required|min:6',
        ]);

        if (!$this->passesHumanCheck($request)) {
            return back()->withInput($request->only('phone'));
        }

        $guard = Auth::guard('delivery_men');
        $provider = $guard->getProvider();

        // Credentials and account gates are checked *before* the session is
        // established, so a suspended or unapproved rider never gets a logged-in
        // session even briefly. It also avoids SessionGuard::logout(), which on
        // the database provider's GenericUser throws on the missing
        // remember_token attribute (delivery_men has no such column).
        $rider = $provider->retrieveByCredentials(['phone' => $request->phone]);

        if (!$rider || !$provider->validateCredentials($rider, ['password' => $request->password])) {
            return back()
                ->withInput($request->only('phone'))
                ->withErrors(['phone' => translate('messages.Incorrect_credential,_please_try_again')]);
        }

        if ($rider->application_status !== 'approved') {
            return back()
                ->withInput($request->only('phone'))
                ->withErrors(['phone' => translate('messages.Your_registration_is_not_approved_yet._You_can_login_once_admin_approved_the_request')]);
        }

        if (!$rider->status) {
            return back()
                ->withInput($request->only('phone'))
                ->withErrors(['phone' => translate('messages.your_account_has_been_suspended')]);
        }

        // No "remember me" on purpose: the delivery_men table has no
        // remember_token column, so a persistent login would fail.
        $guard->login($rider);

        // Session fixation: the pre-login session id must not survive the
        // transition from guest to authenticated.
        $request->session()->regenerate();

        return redirect()->route('rider.dashboard');
    }

    public function logout(Request $request)
    {
        // logoutCurrentDevice() clears the session and the in-memory user but
        // does not cycle a remember token. The full logout() would call
        // GenericUser::getRememberToken() on a missing column and throw.
        Auth::guard('delivery_men')->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rider.login');
    }
}
