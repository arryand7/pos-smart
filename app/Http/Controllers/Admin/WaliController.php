<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wali;
use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WaliController extends Controller
{
    public function index(Request $request): View
    {
        $query = Wali::query()->withCount('santris')->latest();

        $walis = $query->get();

        return view('admin.wali.index', compact('walis'));
    }

    public function create(): View
    {
        return view('admin.wali.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create user account first
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => UserRole::WALI,
            'password' => Hash::make($validated['password']),
        ]);

        // Create wali record
        Wali::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? null,
        ]);

        return redirect()->route('admin.wali.index')
            ->with('status', 'Data wali berhasil ditambahkan.');
    }

    public function edit(Wali $wali): View
    {
        $wali->load('santris');
        return view('admin.wali.edit', compact('wali'));
    }

    public function update(Request $request, Wali $wali): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('walis', 'email')->ignore($wali->id)],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $wali->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? $wali->address,
        ]);

        // Update linked user account if exists
        if ($wali->user) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $wali->user->update($userData);
        }

        return redirect()->route('admin.wali.index')
            ->with('status', 'Data wali berhasil diperbarui.');
    }

    public function destroy(Wali $wali): RedirectResponse
    {
        // Check if wali has santris
        if ($wali->santris()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus wali yang masih memiliki santri.');
        }

        // Delete linked user account
        if ($wali->user) {
            $wali->user->delete();
        }

        $wali->delete();

        return redirect()->route('admin.wali.index')
            ->with('status', 'Data wali berhasil dihapus.');
    }
}
