<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\AppSettingManager;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function redirect(Request $request)
    {
        $config = $this->config();

        if (!$config['client_id'] || !$config['client_secret'] || !$config['redirect_uri']) {
            return redirect()->route('auth.login')->with('error', 'SSO belum dikonfigurasi. Silakan hubungi admin.');
        }

        $state = Str::random(40);
        $request->session()->put('sso_state', $state);
        $request->session()->put('sso_intended', $request->input('intended'));

        $authorizeUrl = $config['base_url'].'/oauth/authorize?'.http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => $config['scopes'],
            'state' => $state,
        ]);

        return redirect()->away($authorizeUrl);
    }

    public function callback(Request $request)
    {
        if (!$this->validState($request)) {
            return $this->fail();
        }

        $code = $request->input('code');
        if (!$code) {
            return $this->fail('Kode otorisasi tidak ditemukan.');
        }

        $config = $this->config();
        $tokenResponse = Http::asForm()->post($config['base_url'].'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            return $this->fail('Gagal menukar token SSO.');
        }

        $accessToken = $tokenResponse->json('access_token');
        if (!$accessToken) {
            return $this->fail('Access token tidak ditemukan.');
        }

        $userInfoResponse = Http::withToken($accessToken)->get($config['base_url'].'/oauth/userinfo');
        if (!$userInfoResponse->successful()) {
            return $this->fail('Gagal mengambil profil SSO.');
        }

        $claims = $userInfoResponse->json();
        $sub = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;

        if (!$sub) {
            return $this->fail('SSO tidak mengembalikan data pengguna yang valid.');
        }

        $user = User::where('sso_sub', $sub)->first();
        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            return $this->fail('Akun Anda belum terdaftar di aplikasi ini.');
        }

        if (!$user->sso_sub) {
            $user->forceFill([
                'sso_sub' => $sub,
                'sso_synced_at' => now(),
            ])->save();
        }

        $token = $user->createToken(
            'sso-web',
            $this->abilitiesForUser($user)
        );

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value ?? $user->role,
            'roles' => $user->roles ?? [],
        ];

        session()->put('smart_token', $token->plainTextToken);
        session()->put('smart_user', $payload);
        session()->flash('smart_bootstrap', [
            'token' => $token->plainTextToken,
            'user' => $payload,
        ]);

        $intended = $request->session()->pull('sso_intended');
        if ($intended && filter_var($intended, FILTER_VALIDATE_URL)) {
            return redirect($intended);
        }

        return redirect($this->routeForRole($payload['role'] ?? null));
    }

    protected function config(): array
    {
        $setting = AppSettingManager::current();
        $baseUrl = $setting->sso_base_url ?: config('sso.base_url');
        $scopes = $setting->sso_scopes ?: config('sso.scopes', 'openid profile email roles');

        return [
            'base_url' => $baseUrl ? rtrim($baseUrl, '/') : null,
            'client_id' => $setting->sso_client_id ?: config('sso.client_id'),
            'client_secret' => $setting->sso_client_secret ?: config('sso.client_secret'),
            'redirect_uri' => $setting->sso_redirect_uri ?: config('sso.redirect_uri'),
            'scopes' => $scopes,
        ];
    }

    protected function validState(Request $request): bool
    {
        $state = (string) $request->input('state');
        $expected = (string) $request->session()->pull('sso_state');

        return $state !== '' && $expected !== '' && hash_equals($expected, $state);
    }

    protected function fail(string $message = 'SSO login gagal. Silakan coba lagi.'): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('auth.login')->with('error', $message);
    }

    /**
     * Map role to default token abilities.
     *
     * @return array<int, string>
     */
    protected function abilitiesForUser(User $user): array
    {
        if ($user->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMIN)) {
            return ['*'];
        }

        $abilities = [];

        if ($user->hasRole(UserRole::BENDAHARA)) {
            $abilities = array_merge($abilities, ['reports:view', 'wallet:view', 'journal:view']);
        }

        if ($user->hasRole(UserRole::KASIR)) {
            $abilities = array_merge($abilities, ['pos:manage', 'wallet:topup', 'wallet:view']);
        }

        if ($user->hasRole(UserRole::WALI)) {
            $abilities = array_merge($abilities, ['wallet:view', 'wallet:topup']);
        }

        if ($user->hasRole(UserRole::SANTRI)) {
            $abilities = array_merge($abilities, ['wallet:view-self']);
        }

        return array_values(array_unique($abilities));
    }

    protected function routeForRole(?string $role): string
    {
        return match ($role) {
            UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value => route('admin.dashboard'),
            'bendahara' => route('dashboard.finance'),
            'kasir' => route('pos'),
            'wali' => route('portal.wali'),
            'santri' => route('portal.santri'),
            default => route('auth.login'),
        };
    }
}
