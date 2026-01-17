<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function topUp(Request $request, Santri $santri): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'provider' => ['nullable', 'string'],
            'channel' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $provider = $data['provider'] ?? 'cash';

        if ($provider === 'cash') {
            $transaction = $this->walletService->credit($santri, $data['amount'], [
                'performed_by' => $request->user()?->id,
                'channel' => $data['channel'] ?? 'cash',
                'description' => $data['description'] ?? 'Top up tunai',
                'metadata' => $data['metadata'] ?? null,
            ]);

            return response()->json([
                'type' => 'wallet',
                'transaction' => $transaction,
            ], 201);
        }

        $payload = array_merge($data['metadata'] ?? [], Arr::only($data, ['channel']));

        $payment = $this->paymentService->initiateTopUp($santri, $data['amount'], $provider, $payload);

        return response()->json([
            'type' => 'payment',
            'payment' => $payment,
        ], 201);
    }

    public function history(Request $request, Santri $santri): JsonResponse
    {
        $this->authorizeWalletView($request->user(), $santri);

        $transactions = $santri->walletTransactions()
            ->latest('occurred_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($transactions);
    }

    protected function authorizeWalletView(?User $user, Santri $santri): void
    {
        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if ($user->hasAnyRole(
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::BENDAHARA->value,
            UserRole::KASIR->value
        )) {
            return;
        }

        if ($user->hasRole(UserRole::SANTRI->value)) {
            if (! $user->santri || $user->santri->getKey() !== $santri->getKey()) {
                abort(403, 'Anda tidak dapat melihat saldo santri lain.');
            }

            return;
        }

        if ($user->hasRole(UserRole::WALI->value)) {
            $isGuardian = $user->wali?->santris()->whereKey($santri->getKey())->exists();

            if (! $isGuardian) {
                abort(403, 'Santri ini tidak berada dalam asuhan Anda.');
            }

            return;
        }

        abort(403, 'Role pengguna tidak memiliki akses ke riwayat dompet ini.');
    }
}
