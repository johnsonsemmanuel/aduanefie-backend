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

        // A retired host has no routes of its own: every read is sent to the
        // host that now owns the surface. This runs before StartSession so the
        // redirect does not mint a cookie for a host that is going away. Writes
        // are refused rather than redirected, so a stale form cannot silently
        // become a GET.
        if (!empty($portal['redirect_to'])) {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                $path = trim($request->path(), '/');
                $target = rtrim($portal['redirect_to'], '/')
                    . ($path === '' ? ($portal['redirect_path'] ?? '/') : '/' . $path);

                if ($request->getQueryString()) {
                    $target .= '?' . $request->getQueryString();
                }

                return redirect()->away($target, 301);
            }

            abort(404);
        }

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
