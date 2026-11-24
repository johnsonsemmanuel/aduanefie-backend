<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesHuman;
use App\Models\Admin;
use App\Services\AdminMfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    use VerifiesHuman;

    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            if (app(AdminMfaService::class)->isVerifiedFor(Auth::guard('admin')->user())) {
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
            'mfa_required' => false,
            'mfa_mode' => null,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (! $this->passesHumanCheck($request)) {
            return back()->withInput($request->only('email', 'remember'));
        }

        $admin = Admin::where('email', $request->email)->where('role_id', 1)->first();

        if (! $admin || ! $admin->password || ! Hash::check((string) $request->password, $admin->password)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Credentials does not match.']);
        }

        $mode = app(AdminMfaService::class)
            ->begin($request, $admin, $request->boolean('remember'));

        return redirect()->route(
            $mode === AdminMfaService::MODE_SETUP
                ? 'admin.mfa.setup'
                : 'admin.mfa.challenge'
        );
    }
}
