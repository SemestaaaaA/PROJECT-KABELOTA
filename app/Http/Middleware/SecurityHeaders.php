<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** Baseline browser protections for every web response. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Same-origin only. Alpine needs 'unsafe-eval'; a few inline handlers need 'unsafe-inline'.
        // Skipped while the Vite dev server serves assets from another port.
        if (! app()->environment('local') && ! Vite::isRunningHot()) {
            $analytics = config('kabelota.analytics.host');
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'".($analytics ? ' '.$analytics : ''),
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'".($analytics ? ' '.$analytics : ''),
                "frame-src 'self'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Keep demo, QA and private pages out of search engines.
        if (! app()->isProduction() || $request->is('admin*', 'akun*', 'profil*', 'perusahaan*', 'tawaran*', 'lamaran*', 'talenta/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
