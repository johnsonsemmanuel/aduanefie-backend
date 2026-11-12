<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\Config;
use App\Models\Module;

class ModuleCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $except = [
            'api/v1/customer*', 'api/v1/banners', 'api/v1/stores/get-stores/*', 'api/v1/coupon/list', 'api/v1/categories', 'api/v1/items/reviews/submit', 'api/v1/delivery-man/reviews/submit'
        ];

        foreach ($except as $except) {
            if ($request->fullUrlIs($except) || $request->is($except)) {
                if(!$request->hasHeader('moduleId')) {
                    return $next($request);
                }
            }
        }

        // Check header request and determine localizaton
        if(!$request->hasHeader('moduleId'))
        {
            $errors = [];
            array_push($errors, ['code' => 'moduleId', 'message' => translate('messages.module_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $activeModules = \Illuminate\Support\Facades\Cache::remember('active_modules_by_id_v1', now()->addMinutes(5), function () {
            return Module::active()->orderBy('id')->get()->keyBy('id');
        });
        $requestedId = (int) $request->header('moduleId');
        $module = $activeModules->get($requestedId);
        if (!$module && $request->isMethod('GET') && $activeModules->isNotEmpty()) {
            $module = $activeModules->first();
        }
        if(!$module) {
            $errors = [];
            array_push($errors, ['code' => 'moduleId', 'message' => translate('messages.not_found')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        Config::set('module.current_module_data', $module);
        return $next($request);
    }
}
