<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'homeUrl' => $this->homeUrl($user?->role?->value ?? $user?->role),
            'homeLabel' => $this->homeLabel($user?->role?->value ?? $user?->role),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('auth.login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check((string) ($data['current_password'] ?? ''), $user->password ?? '')) {
                return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])->withInput();
            }
        }

        $payload = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        $sessionUser = $request->session()->get('smart_user', []);
        $sessionUser['name'] = $user->name;
        $sessionUser['email'] = $user->email;
        $sessionUser['role'] = $user->role?->value ?? $user->role;
        $sessionUser['roles'] = $user->roles ?? [];

        $request->session()->put('smart_user', $sessionUser);
        $request->session()->flash('smart_bootstrap', [
            'token' => $request->session()->get('smart_token'),
            'user' => $sessionUser,
        ]);

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    protected function homeUrl(?string $role): string
    {
        return match ($role) {
            UserRole::SUPER_ADMIN->value => route('admin.dashboard'),
            UserRole::ADMIN->value => route('admin.dashboard'),
            UserRole::BENDAHARA->value => route('dashboard.finance'),
            UserRole::KASIR->value => route('pos'),
            UserRole::WALI->value => route('portal.wali'),
            UserRole::SANTRI->value => route('portal.santri'),
            default => '/',
        };
    }

    protected function homeLabel(?string $role): string
    {
        return match ($role) {
            UserRole::SUPER_ADMIN->value => 'Dashboard Super Admin',
            UserRole::ADMIN->value => 'Dashboard Admin',
            UserRole::BENDAHARA->value => 'Dashboard Keuangan',
            UserRole::KASIR->value => 'Kasir',
            UserRole::WALI->value => 'Portal Wali',
            UserRole::SANTRI->value => 'Portal Santri',
            default => 'Beranda',
        };
    }
}
