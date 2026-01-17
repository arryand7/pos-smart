<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak valid.'],
            ]);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'smart-api',
            $this->abilitiesForUser($user)
        );

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $token->accessToken->abilities ?? [],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value ?? $user->role,
                'roles' => $user->roles ?? [],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Token berhasil dicabut.']);
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
}
