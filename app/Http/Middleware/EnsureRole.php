<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi rute ke peran tertentu, mis. ->middleware('role:owner,manager').
     * Peran global; batasan per proyek menyusul lewat ProjectPolicy (Fase 1).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_filter(array_map(
            fn (string $role) => UserRole::tryFrom($role),
            $roles
        ));

        if ($user === null || ! in_array($user->role, $allowed, true)) {
            abort(403, 'Peran Anda tidak diizinkan mengakses halaman ini.');
        }

        return $next($request);
    }
}
