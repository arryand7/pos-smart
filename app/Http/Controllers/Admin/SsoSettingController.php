<?php

namespace App\Http\Controllers\Admin;

use App\AppSettingManager;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SsoSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.sso-settings', [
            'setting' => AppSettingManager::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sso_base_url' => ['nullable', 'url', 'max:255'],
            'sso_client_id' => ['nullable', 'string', 'max:255'],
            'sso_client_secret' => ['nullable', 'string', 'max:255'],
            'sso_redirect_uri' => ['nullable', 'url', 'max:255'],
            'sso_scopes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var AppSetting $setting */
        $setting = AppSetting::query()->first() ?? new AppSetting();

        $baseUrl = trim((string) ($validated['sso_base_url'] ?? ''));
        $setting->sso_base_url = $baseUrl !== '' ? rtrim($baseUrl, '/') : null;

        $clientId = trim((string) ($validated['sso_client_id'] ?? ''));
        $setting->sso_client_id = $clientId !== '' ? $clientId : null;

        $redirectUri = trim((string) ($validated['sso_redirect_uri'] ?? ''));
        $setting->sso_redirect_uri = $redirectUri !== '' ? $redirectUri : null;

        $scopes = trim((string) ($validated['sso_scopes'] ?? ''));
        $setting->sso_scopes = $scopes !== '' ? $scopes : null;

        if (!empty($validated['sso_client_secret'])) {
            $setting->sso_client_secret = trim((string) $validated['sso_client_secret']);
        }

        $setting->save();
        AppSettingManager::refreshCache();

        return back()->with('status', 'Pengaturan SSO berhasil diperbarui.');
    }
}
