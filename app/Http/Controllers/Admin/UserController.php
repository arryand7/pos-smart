<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use ExportsTable;
    public function index(Request $request)
    {
        $roles = [UserRole::ADMIN, UserRole::BENDAHARA, UserRole::KASIR];

        if ($this->canManageSuperAdmin($request)) {
            $roles[] = UserRole::SUPER_ADMIN;
        }

        $query = User::query()
            ->whereIn('role', $roles);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'name', 'email', 'role', 'created_at' => $builder->orderBy($sort, $direction),
                default => $builder->orderByDesc('created_at'),
            };
        }, fn ($builder) => $builder->orderByDesc('created_at'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (User $user) {
                $roles = collect($user->roles ?? [])
                    ->push($user->role?->value ?? $user->role)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    $user->name,
                    $user->email,
                    strtoupper($roles),
                    $user->created_at?->format('d M Y') ?? '-',
                ];
            })->all();

            $headings = ['Nama', 'Email', 'Role', 'Bergabung'];

            return $this->exportTable($exportType, 'users', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $users = $query->paginate($perPage)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(Request $request): View
    {
        $roles = $this->availableRoles($request);

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $roles = $this->availableRoles($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys($roles))],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(array_keys($roles))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['roles'] = $this->normalizeRoles($validated['role'], $validated['roles'] ?? []);

        User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('status', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): View
    {
        // Prevent editing santri/wali
        if ($user->hasAnyRole(UserRole::SANTRI, UserRole::WALI)) {
            abort(403, 'Tidak dapat mengedit akun santri/wali dari sini.');
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN) && ! $this->canManageSuperAdmin($request)) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit super admin.');
        }

        $roles = $this->availableRoles($request);

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->hasAnyRole(UserRole::SANTRI, UserRole::WALI)) {
            abort(403, 'Tidak dapat mengedit akun santri/wali dari sini.');
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN) && ! $this->canManageSuperAdmin($request)) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit super admin.');
        }

        $roles = $this->availableRoles($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys($roles))],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(array_keys($roles))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['roles'] = $this->normalizeRoles($validated['role'], $validated['roles'] ?? []);

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->hasAnyRole(UserRole::SANTRI, UserRole::WALI)) {
            abort(403, 'Tidak dapat menghapus akun santri/wali dari sini.');
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN) && ! $this->canManageSuperAdmin($request)) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus super admin.');
        }

        // Prevent self-delete
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', 'Pengguna berhasil dihapus.');
    }

    private function canManageSuperAdmin(Request $request): bool
    {
        $user = $request->user();
        $sessionRole = $request->session()->get('smart_user.role');

        return ($user && $user->hasRole(UserRole::SUPER_ADMIN)) || $sessionRole === UserRole::SUPER_ADMIN->value;
    }

    /**
     * @return array<string, string>
     */
    private function availableRoles(Request $request): array
    {
        $roles = [
            UserRole::ADMIN->value => 'Administrator',
            UserRole::BENDAHARA->value => 'Bendahara',
            UserRole::KASIR->value => 'Kasir',
        ];

        if ($this->canManageSuperAdmin($request)) {
            $roles = [UserRole::SUPER_ADMIN->value => 'Super Admin'] + $roles;
        }

        return $roles;
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function normalizeRoles(string $primaryRole, array $roles): array
    {
        $roles = collect($roles)
            ->filter()
            ->map(fn ($role) => (string) $role)
            ->push($primaryRole)
            ->unique()
            ->values()
            ->all();

        return $roles;
    }
}
