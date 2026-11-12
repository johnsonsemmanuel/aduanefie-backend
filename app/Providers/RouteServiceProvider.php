<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';


    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::prefix('admin')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin.php'));

            Route::prefix('vendor-panel')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/vendor.php'));

            // Remote-marketer portal. No prefix: its own paths are
            // /marketer-login, /marketer-mfa and /marketer/*.
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/marketer.php'));

            Route::prefix('api/v1')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v1/api.php'));

            Route::prefix('api/v2')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v2/api.php'));


            //new routes
            Route::prefix('admin')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin/routes.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by(optional($request->user())->id ?: $request->ip());
        });

        // Web logins had no throttling at all. Key on email + IP so one account
        // cannot be sprayed from a single host, and one host cannot enumerate
        // accounts. Tight for admin, looser for the shared store entry point.
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($this->loginThrottleKey($request));
        });

        RateLimiter::for('web-login', function (Request $request) {
            return Limit::perMinute(10)->by($this->loginThrottleKey($request));
        });

        // Canonical store login. Same budget as the legacy shared entry so the
        // new front door cannot become the cheaper way to spray credentials.
        RateLimiter::for('store-login', function (Request $request) {
            return Limit::perMinute(10)->by($this->loginThrottleKey($request));
        });

        // Marketer portal login is email based, like the admin one, and gets
        // the same tight limit. The portal is a door into admin accounts, so it
        // must not be easier to brute-force than /admin-login.
        RateLimiter::for('marketer-login', function (Request $request) {
            return Limit::perMinute(5)->by('marketer|' . $this->loginThrottleKey($request));
        });

        // Riders sign in by phone, so the key has to read the phone field or
        // every rider would share the empty-string bucket and lock each other
        // out after five attempts from the same IP.
        RateLimiter::for('rider-login', function (Request $request) {
            return Limit::perMinute(5)->by($this->loginThrottleKey($request, 'phone'));
        });

        // The second-factor challenge is a 6-digit space. Key on the pending
        // admin id (not just the IP) so one account cannot be brute-forced and
        // one shared office IP cannot lock everyone else out.
        RateLimiter::for('admin-mfa', function (Request $request) {
            $pending = $request->session()->get(\App\Services\AdminMfaService::SESSION_PENDING);
            $id = is_array($pending) ? (string) ($pending['id'] ?? 'guest') : 'guest';

            return Limit::perMinute(6)->by('admin-mfa|' . $id . '|' . $request->ip());
        });
    }

    private function loginThrottleKey(Request $request, string $field = 'email'): string
    {
        return Str::transliterate(Str::lower((string) $request->input($field)) . '|' . $request->ip());
    }
}
