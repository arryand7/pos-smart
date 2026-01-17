<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Santri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuardianSantriController extends Controller
{
    public function updateLimits(Request $request, Santri $santri): RedirectResponse
    {
        $user = $request->user();
        $wali = $user?->wali;

        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if (! $user->hasRole(UserRole::SUPER_ADMIN) && (! $wali || $santri->wali_id !== $wali->id)) {
            abort(403, 'Anda tidak dapat memperbarui data santri tersebut.');
        }

        $data = $request->validate([
            'daily_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0'],
            'is_wallet_locked' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $santri->update([
            'daily_limit' => $data['daily_limit'] ?? $santri->daily_limit,
            'monthly_limit' => $data['monthly_limit'] ?? $santri->monthly_limit,
            'is_wallet_locked' => (bool) ($data['is_wallet_locked'] ?? false),
            'notes' => $data['notes'] ?? $santri->notes,
        ]);

        return back()->with('status', "{$santri->name} berhasil diperbarui.");
    }
}
