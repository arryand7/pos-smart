<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandingSettingController extends Controller
{
    public function edit(): View
    {
        $settings = AppSetting::first() ?? new AppSetting();

        $timezones = [
            'Asia/Jakarta' => 'WIB (Asia/Jakarta)',
            'Asia/Makassar' => 'WITA (Asia/Makassar)',
            'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
            'UTC' => 'UTC',
        ];

        return view('admin.settings.branding', compact('settings', 'timezones'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        // Handle logo upload
        if ($request->hasFile('app_logo')) {
            $validated['app_logo'] = $request->file('app_logo')->store('branding', 'public');
        }

        $settings = AppSetting::firstOrCreate([]);
        $settings->update($validated);

        return redirect()->route('admin.settings.branding')
            ->with('status', 'Pengaturan branding berhasil disimpan.');
    }
}
