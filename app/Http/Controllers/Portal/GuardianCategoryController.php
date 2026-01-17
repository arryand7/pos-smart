<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Santri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuardianCategoryController extends Controller
{
    public function update(Request $request, Santri $santri): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if (! $user->hasRole(UserRole::SUPER_ADMIN) && (! $user->wali || $santri->wali_id !== $user->wali->id)) {
            abort(403, 'Anda tidak memiliki akses ke santri ini.');
        }

        $validated = $request->validate([
            'blocked_category_ids' => ['nullable', 'array'],
            'blocked_category_ids.*' => ['integer', 'exists:product_categories,id'],
            'whitelisted_category_ids' => ['nullable', 'array'],
            'whitelisted_category_ids.*' => ['integer', 'exists:product_categories,id'],
        ]);

        $santri->update([
            'blocked_category_ids' => $validated['blocked_category_ids'] ?? [],
            'whitelisted_category_ids' => $validated['whitelisted_category_ids'] ?? [],
        ]);

        $redirectParams = $user->hasRole(UserRole::SUPER_ADMIN) ? ['wali_id' => $santri->wali_id] : [];

        return redirect()
            ->route('portal.wali', $redirectParams)
            ->with('status', 'Pengaturan kategori berhasil disimpan.');
    }
}
