<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Vendor;
use App\Models\VendorEmployee;

class VendorTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!is_string($token) || $token === '') {
            return response()->json([
                'errors' => [
                    ['code' => 'auth-001', 'message' => 'Unauthorized.']
                ]
            ], 401);
        }
        if (!$request->hasHeader('vendorType')) {
            $errors = [];
            array_push($errors, ['code' => 'vendor_type', 'message' => translate('messages.vendor_type_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $unauthorized = response()->json([
            'errors' => [
                ['code' => 'auth-001', 'message' => 'Unauthorized.']
            ]
        ], 401);

        $vendor_type = $request->header('vendorType');
        if ($vendor_type === 'owner') {
            $vendor = Vendor::where('auth_token', $token)->first();
            if (!isset($vendor)) {
                return $unauthorized;
            }
            $request['vendor'] = $vendor;
            Config::set('module.current_module_data', $vendor->stores[0]->module);
        } elseif ($vendor_type === 'employee') {
            $employee = VendorEmployee::where('auth_token', $token)->first();
            if (!isset($employee) || !isset($employee->vendor)) {
                return $unauthorized;
            }
            $request['vendor'] = $employee->vendor;
            $request['vendor_employee'] = $employee;
            Config::set('module.current_module_data', $employee->vendor->stores[0]->module);
        } else {
            // Default deny: an unknown vendorType must never reach a downstream
            // controller with an unauthenticated (or missing) vendor identity.
            return $unauthorized;
        }

        return $next($request);
    }
}
