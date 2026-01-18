<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use Illuminate\Http\Request;

class SantriController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Santri::class, 'santri');
    }

    public function index(Request $request)
    {
        $query = Santri::with(['wali', 'user'])->orderBy('name');

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('qr_code', 'like', "%{$search}%");
            });
        }

        return view('admin.santri.index', [
            'santris' => $query->paginate(15)->withQueryString(),
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
