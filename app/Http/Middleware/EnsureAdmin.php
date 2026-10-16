<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin-only endpoints called from the public site (Mode CMS). Everyone else gets 403, never a redirect. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Khusus admin.');

        return $next($request);
    }
}
