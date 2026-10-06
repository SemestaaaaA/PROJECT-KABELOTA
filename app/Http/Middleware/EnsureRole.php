<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:perusahaan') or 'role:talenta'. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->role !== $role) {
            return redirect()->route('home')->with('status', $role === 'perusahaan'
                ? 'Halaman ini khusus akun perusahaan.'
                : 'Halaman ini khusus akun talenta.');
        }

        return $next($request);
    }
}
