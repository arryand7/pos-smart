<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthBridgeController extends Controller
{
    public function __construct(private readonly AuthController $authController)
    {
    }

    public function login(Request $request): RedirectResponse
    {
        try {
            $response = $this->authController->login($request);
        } catch (ValidationException $exception) {
            return back()->with('error', 'Email atau password tidak valid.')->withInput($request->only('email'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', 'Terjadi kesalahan saat mengautentikasi.')->withInput($request->only('email'));
        }

        $data = $response->getData(true);

        $token = $data['token'] ?? null;
        $user = $data['user'] ?? [];

        if (! $token) {
            return back()->with('error', 'Gagal mengautentikasi.')->withInput($request->only('email'));
        }

        if ($userModel = $this->resolveUserModel($user)) {
            Auth::login($userModel);
            ActivityLog::log('logged_in', 'Login via password.', $userModel);
        }

        session()->put('smart_token', $token);
        session()->put('smart_user', $user);
        session()->flash('smart_bootstrap', [
            'token' => $token,
            'user' => $user,
        ]);

        return redirect($this->routeForRole($user['role'] ?? null));
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::log('logged_out', 'Logout');
        Auth::logout();

        if ($token = $request->session()->pull('smart_token')) {
            if ($accessToken = PersonalAccessToken::findToken($token)) {
                $accessToken->delete();
            }
        }

        $request->session()->forget(['smart_user', 'smart_bootstrap']);

        return redirect()->route('auth.login')->with('status', 'Anda telah keluar.');
    }

    protected function routeForRole(?string $role): string
    {
        return match ($role) {
            'super_admin', 'admin' => route('admin.dashboard'),
            'bendahara' => route('dashboard.finance'),
            'kasir' => route('pos'),
            'wali' => route('portal.wali'),
            'santri' => route('portal.santri'),
            default => route('auth.login'),
        };
    }

    protected function resolveUserModel(array $user): ?User
    {
        $userId = $user['id'] ?? null;
        if ($userId) {
            return User::find($userId);
        }

        $email = $user['email'] ?? null;

        return $email ? User::where('email', $email)->first() : null;
    }
}
