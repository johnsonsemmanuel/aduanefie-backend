<?php

namespace App\Http\Middleware;

use App\Services\PreviewAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsurePreviewAccess
{
    /**
     * API path patterns that must remain reachable while public access is closed:
     *  - waitlist submission & access-code verification
     *  - minimal branding/SSR config & health check
     *  - inbound farmer SMS webhook
     *  - dedicated vendor/deliveryman mobile app endpoints (guarded by their own tokens)
     * Note: Payment gateway callbacks (`/payment/paystack/*`, `/payment/flutterwave-v3/*`,
     * `/payment/stripe/*`, `/payment/paypal/*`, etc.) live on the `web` middleware group
     * and are completely unaffected by this middleware.
     */
    private const EXEMPT_API_PATTERNS = [
        'api/v1/waitlist',
        'api/v1/waitlist/*',
        'api/v1/config',
        'api/v1/get-page-meta-data',
        'api/v1/react-landing-page',
        'api/v1/health',
        'api/v1/sms/inbound/*',
        'api/v1/vendor/*',
        'api/v1/delivery-man/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        /** @var PreviewAccessService $preview */
        $preview = app(PreviewAccessService::class);
        if ($preview->isPublicAccessOpen()) {
            return $next($request);
        }

        if ($request->is(...self::EXEMPT_API_PATTERNS)) {
            return $next($request);
        }

        $token = $request->header(PreviewAccessService::HEADER_NAME)
            ?: $request->cookie(PreviewAccessService::COOKIE_NAME);

        if ($preview->verifyToken(is_string($token) ? $token : null)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Aduanefie is currently in private preview. Join the waitlist or enter your owner access code.',
            'code' => 'preview_access_required',
        ], 403);
    }
}
