<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Allow only users whose role is in the given list.
     * Usage: ->middleware('role:super_admin') or 'role:super_admin,kepala_unit'
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Support "role:a|b" or "role:a,b"
        if (count($roles) === 1 && (str_contains($roles[0], '|') || str_contains($roles[0], ','))) {
            $roles = preg_split('/[|,]/', $roles[0]);
        }

        $user = $request->user();

        // Pastikan sudah login (biasanya sudah karena kamu juga pakai 'auth')
        if (!$user) {
            return redirect()->route('login');
        }

        // Izinkan jika user memiliki salah satu role yang diminta
        $has = collect($roles)->some(fn($r) => $user->hasRole((string)$r));
        if (!$has) abort(403, 'Anda tidak berhak mengakses halaman ini.');

        return $next($request);
    }
}
