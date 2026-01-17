<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $sessionUser = $request->session()->get('smart_user');
        $authUser = $request->user();

        if (! $sessionUser && ! $authUser) {
            return redirect()->route('auth.login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if (! $authUser && $sessionUser && isset($sessionUser['id'])) {
            $authUser = User::find($sessionUser['id']);

            if ($authUser) {
                Auth::login($authUser);
            }
        }

        $role = data_get($sessionUser, 'role')
            ?? ($authUser?->role?->value ?? $authUser?->role);

        if ($role === UserRole::SUPER_ADMIN->value || $authUser?->hasRole(UserRole::SUPER_ADMIN)) {
            return $next($request);
        }
        $normalizedRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', (string) $role))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->values();

        if ($normalizedRoles->isEmpty()) {
            return $next($request);
        }

        if ($authUser && $authUser->hasAnyRole(...$normalizedRoles->all())) {
            return $next($request);
        }

        $sessionRoles = collect(data_get($sessionUser, 'roles', []))
            ->map(fn ($item) => $item instanceof UserRole ? $item->value : (string) $item)
            ->filter();

        if ($sessionRoles->intersect($normalizedRoles)->isNotEmpty()) {
            return $next($request);
        }

        if ($normalizedRoles->contains($role)) {
            return $next($request);
        }

        return redirect($this->routeForRole($role) ?? route('auth.login'))
            ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
    }

    protected function routeForRole(?string $role): ?string
    {
        return match ($role) {
            UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value => route('admin.dashboard'),
            'bendahara' => route('dashboard.finance'),
            'kasir' => route('pos'),
            'wali' => route('portal.wali'),
            'santri' => route('portal.santri'),
            default => null,
        };
    }
}
