<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function check()
    {
        $dbOk = false;
        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $logsWritable = is_writable(storage_path('logs'));
        $passportKeysPresent = file_exists(storage_path('oauth-private.key'))
            && file_exists(storage_path('oauth-public.key'));

        $activeSmsProvider = null;
        try {
            if (Schema::hasTable('addon_settings')) {
                $active = DB::table('addon_settings')
                    ->where('settings_type', 'sms_config')
                    ->where('is_active', 1)
                    ->value('key_name');
                if ($active) {
                    $activeSmsProvider = $active;
                }
            }
            if (! $activeSmsProvider && (
                ! empty(env('INTEKHOST_API_KEY'))
                || ! empty(BusinessSetting::where('key', 'intekhost_api_key')->value('value'))
            )) {
                $activeSmsProvider = 'intekhost';
            }
        } catch (\Throwable $e) {
            $activeSmsProvider = null;
        }

        $healthy = $dbOk && $logsWritable && $passportKeysPresent;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $dbOk,
                'storage_logs_writable' => $logsWritable,
                'passport_keys' => $passportKeysPresent,
                'sms_provider' => $activeSmsProvider ?? 'none_configured',
            ],
        ], $healthy ? 200 : 503);
    }
}
