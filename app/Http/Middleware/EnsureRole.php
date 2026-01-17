<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return $next($request);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', (string) $role))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if (empty($allowedRoles)) {
            return $next($request);
        }

        if (! $user->hasAnyRole(...$allowedRoles)) {
            abort(403, 'Role pengguna tidak memiliki akses ke sumber daya ini.');
        }

        return $next($request);
    }
}
