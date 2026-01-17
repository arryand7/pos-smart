<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Santri;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SantriPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole(UserRole::SUPER_ADMIN) ?? false;

        if (! $user || (! $user->santri && ! $isSuperAdmin)) {
            abort(403, 'Akun santri tidak ditemukan.');
        }

        $activeSantriId = null;
        $santriOptions = collect();

        if ($isSuperAdmin) {
            $activeSantriId = $request->integer('santri_id');
            $santri = $activeSantriId
                ? Santri::with(['wali'])->find($activeSantriId)
                : Santri::with(['wali'])->orderBy('name')->first();

            if (! $santri) {
                abort(404, 'Belum ada data santri.');
            }

            $santriOptions = Santri::orderBy('name')->get(['id', 'name', 'nis']);
        } else {
            $santri = $user->santri()->with(['wali'])->firstOrFail();
        }

        $walletHistory = $santri->walletTransactions()->latest('occurred_at')->limit(10)->get();
        $posHistory = $santri->transactions()->latest('processed_at')->limit(5)->get();
        $payments = $santri->payments()->latest('created_at')->limit(5)->get();

        return view('portal.santri', [
            'santri' => $santri,
            'walletHistory' => $walletHistory,
            'posHistory' => $posHistory,
            'payments' => $payments,
            'isSuperAdmin' => $isSuperAdmin,
            'santriOptions' => $santriOptions,
            'activeSantriId' => $activeSantriId ?? $santri?->id,
        ]);
    }
}
