<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\Request;

class SantriController extends Controller
{
    use ExportsTable;
    public function __construct()
    {
        $this->authorizeResource(Santri::class, 'santri');
    }

    public function index(Request $request)
    {
        $query = Santri::with(['wali', 'user']);

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('qr_code', 'like', "%{$search}%")
                    ->orWhereHas('wali', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'name', 'nis', 'qr_code', 'wallet_balance', 'daily_limit', 'weekly_limit', 'monthly_limit', 'is_wallet_locked' => $builder->orderBy($sort, $direction),
                'wali' => $builder->orderBy(
                    \App\Models\Wali::select('name')->whereColumn('walis.id', 'santris.wali_id'),
                    $direction
                ),
                default => $builder->orderBy('name'),
            };
        }, fn ($builder) => $builder->orderBy('name'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (Santri $santri) {
                return [
                    $santri->name,
                    $santri->nis,
                    $santri->qr_code ?? '-',
                    $santri->wali?->name ?? '-',
                    number_format($santri->wallet_balance, 0, ',', '.'),
                    number_format($santri->daily_limit ?? 0, 0, ',', '.'),
                    number_format($santri->weekly_limit ?? 0, 0, ',', '.'),
                    number_format($santri->monthly_limit ?? 0, 0, ',', '.'),
                    $santri->is_wallet_locked ? 'Diblokir' : 'Aktif',
                ];
            })->all();

            $headings = ['Santri', 'NIS', 'QR Code', 'Wali', 'Saldo', 'Limit Harian', 'Limit Mingguan', 'Limit Bulanan', 'Status Dompet'];

            return $this->exportTable($exportType, 'santri', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return view('admin.santri.index', [
            'santris' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    public function edit(Santri $santri)
    {
        return view('admin.santri.edit', compact('santri'));
    }

    public function update(Request $request, Santri $santri)
    {
        $data = $request->validate([
            'qr_code' => ['nullable', 'string', 'max:120', 'unique:santris,qr_code,'.$santri->id],
            'daily_limit' => ['nullable', 'numeric', 'min:0'],
            'weekly_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0'],
            'is_wallet_locked' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $santri->update($data);

        return redirect()->route('admin.santri.index')->with('status', 'Data santri diperbarui.');
    }
}
