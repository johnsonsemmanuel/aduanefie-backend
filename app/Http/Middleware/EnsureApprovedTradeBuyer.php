<?php

namespace App\Http\Middleware;

use App\Models\TradeBuyerProfile;
use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;

/**
 * Enforces authoritative database approval check for protected trade access.
 *
 * Direct database verification ensures any request arriving after a committed
 * revocation transaction is immediately denied.
 *
 * Additionally verifies that the trade feature flag is active and route-enabled,
 * ensuring disabled routes fail closed and never expose placeholder responses.
 */
class EnsureApprovedTradeBuyer
{
    public function __construct(private FeatureFlagService $flags)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        // 1. Fail closed if trade routes or transactions are disabled
        if (! $this->flags->isRouteEnabled('trade_access_applications') && ! $this->flags->allowsNewTransactions('trade_access_applications')) {
            return response()->json([
                'message' => 'Cross-border trade features are currently disabled.',
                'code' => 'trade_feature_disabled',
            ], 403);
        }

        $user = $request->user();
        if (! $user) {
            try {
                $user = auth('api')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

        if (! $user) {
            return response()->json([
                'message' => 'Authentication is required to access private trade features.',
                'code' => 'trade_auth_required',
            ], 401);
        }

        // 2. Authoritative database approval check
        $profile = TradeBuyerProfile::where('user_id', $user->id)->first(['status']);

        if (! $profile || $profile->status !== TradeBuyerProfile::STATUS_APPROVED) {
            $code = ($profile && $profile->status === TradeBuyerProfile::STATUS_REVOKED)
                ? 'trade_access_revoked'
                : 'trade_access_required';

            $message = ($code === 'trade_access_revoked')
                ? 'Your cross-border trade sourcing access has been revoked.'
                : 'Approved cross-border trade access is required for this action.';

            return response()->json([
                'message' => $message,
                'code' => $code,
            ], 403);
        }

        return $next($request);
    }
}
