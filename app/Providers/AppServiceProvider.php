<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Traits\AddonHelper;
use Illuminate\Database\Connection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\CentralLogics\Helpers;

class AppServiceProvider extends ServiceProvider
{
    use AddonHelper;
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Geometry SRID depends on the runtime driver: MySQL keeps SRID 0 while
        // PostGIS zones are stored as SRID 4326. Defined here because the .env
        // file is not loaded yet when composer autoloads app/Library/Constants.php.
        if (!defined('POINT_SRID')) {
            define('POINT_SRID', (int) env('DB_POINT_SRID', config('database.default') === 'pgsql' ? 4326 : 0));
        }

        // Keep LIKE case-insensitive on Postgres. The stock grammar casts the
        // column to ::text, which defeats the citext columns that reproduce
        // MySQL's case-insensitive comparisons. MySQL keeps its own grammar.
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new \App\Database\PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('app.env') === 'production' || env('FORCE_HTTPS', true)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        try
        {
            $bootData = Cache::remember('boot_app_provider_v1', now()->addMinutes(3), function () {
                return [
                    'addon_admin_routes' => $this->get_addon_admin_routes(),
                    'get_payment_publish_status' => $this->get_payment_publish_status(),
                    'view_keys' => Helpers::get_view_keys(),
                ];
            });
            Config::set('addon_admin_routes', $bootData['addon_admin_routes']);
            Config::set('get_payment_publish_status', $bootData['get_payment_publish_status']);
            Paginator::useBootstrap();
            foreach ($bootData['view_keys'] as $key => $value) {
                view()->share($key, $value);
            }
        }
        catch(\Exception $e)
        {

        }

    }
}
