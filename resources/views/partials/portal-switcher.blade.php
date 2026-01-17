@php
    $sessionRole = data_get(session('smart_user'), 'role');
    $sessionRoles = collect(data_get(session('smart_user'), 'roles', []))->filter()->values();
    $userRole = auth()->user()?->role?->value ?? auth()->user()?->role;
    $userRoles = collect(auth()->user()?->roles ?? [])->filter()->values();
    $role = $sessionRole ?: $userRole;
    $roles = $sessionRoles->isNotEmpty() ? $sessionRoles : $userRoles;
    $isSuperAdmin = $role === \App\Enums\UserRole::SUPER_ADMIN->value || $roles->contains(\App\Enums\UserRole::SUPER_ADMIN->value);
    $hasMultiRole = $roles->unique()->count() > 1;
@endphp

@if($isSuperAdmin || $hasMultiRole)
    <div class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Portal Cepat</p>
                <p class="text-sm text-slate-600">Masuk ke modul sesuai peran tanpa keluar.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-sm">Admin</a>
                <a href="{{ route('dashboard.finance') }}" class="btn btn-outline btn-sm">Keuangan</a>
                <a href="{{ route('pos') }}" class="btn btn-outline btn-sm">POS</a>
                <a href="{{ route('portal.wali') }}" class="btn btn-outline btn-sm">Portal Wali</a>
                <a href="{{ route('portal.santri') }}" class="btn btn-outline btn-sm">Portal Santri</a>
            </div>
        </div>
    </div>
@endif
