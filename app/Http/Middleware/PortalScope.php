<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gives every portal host its own scope.
 *
 * This middleware is prepended to the `web` group so it runs before
 * StartSession. That timing is deliberate: the session store reads its cookie
 * name from config when it boots, so the per-host cookie has to be set first.
 *
 * This is defence in depth, not authorization. Every route still has to enforce
 * its own guard; the host check only removes routes the portal has no business
 * serving.
 */
class PortalScope
{
    public function handle(Request $request, Closure $next)
    {
        // Hostnames contain dots, so they cannot be addressed with dot notation
        // ("portals.hosts.store.aduanefie.com" would be read as nested keys).
        // Resolve the map first, then index it literally.
        $hosts = config('portals.hosts', []);
        $portalKey = $hosts[$request->getHost()] ?? config('portals.default', 'default');

        $portals = config('portals.portals', []);
        $portal = $portals[$portalKey] ?? [];

        // One cookie name per host, host-only domain. Never let a session cookie
        // be shared across subdomains: an admin session must not be readable by
        // the storefront host.
        if (!empty($portal['session_cookie'])) {
            config([
                'session.cookie' => $portal['session_cookie'],
                'session.domain' => null,
            ]);
        }

        $deny = $portal['deny'] ?? [];
        if (!empty($deny) && $request->is(...$deny)) {
            abort(404);
        }

        if ($request->isMethod('GET') && $request->path() === '/' && !empty($portal['entry'])) {
            return redirect($portal['entry']);
        }

        return $next($request);
    }
}
