<?php

namespace Modules\Service\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $moduleNamespace = 'Modules\Service\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->prefix('admin')
            ->as('admin.')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', '/Routes/web/admin/admin.php'));

        Route::middleware('web')
            ->prefix('vendor-panel')
            ->as('vendor.')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', '/Routes/web/vendor/routes.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', '/Routes/api/v1/provider/api.php'));

        Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', '/Routes/api/v1/public/api.php'));
    }
}
