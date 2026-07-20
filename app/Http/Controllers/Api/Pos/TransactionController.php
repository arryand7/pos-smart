<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\POS\PosService;
use App\Services\POS\PosTransactionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $transactions = Transaction::query()
            ->with([
                'items:id,transaction_id,product_name,quantity,unit_price,subtotal',
                'santri:id,name,nis',
                'kasir:id,name',
                'location:id,name,code',
            ])
            ->when(
                $request->user()?->hasRole(UserRole::KASIR)
                    && ! $request->user()?->hasAnyRole(UserRole::ADMIN, UserRole::SUPER_ADMIN),
                fn ($q) => $q->where('location_id', $request->user()->location_id ?? 0),
            )
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('processed_at')
            ->paginate($perPage);

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_transaction_id' => ['required', 'uuid'],
            'location_id' => ['required', 'exists:locations,id'],
            'santri_id' => ['nullable', 'exists:santris,id'],
            'payment_method' => ['nullable', 'in:wallet,cash,gateway'],
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'gateway_provider' => ['nullable', 'string', 'max:30'],
            'kasir_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $actingUser = $request->user();

        if (! $actingUser) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        $kasir = $this->resolveKasir($actingUser, $data['kasir_id'] ?? null);

        try {
            $transaction = $this->posService->createTransaction($data, $kasir);
        } catch (PosTransactionException $exception) {
            Log::notice('SMART POS transaction rejected', [
                'client_transaction_id' => $data['client_transaction_id'],
                'stage' => 'transaction_rolled_back',
                'exception' => $exception::class,
                'error_code' => $exception->errorCode,
            ]);

            return response()->json(['success' => false, 'code' => $exception->errorCode,
                'message' => $exception->getMessage(), 'errors' => $exception->details], $exception->httpStatus);
        }

        if ($transaction->primary_payment_method === 'wallet' && $transaction->santri) {
            $balanceAfter = (int) $transaction->santri->wallet_balance;
            $transaction->setAttribute('wallet_balance_before', $balanceAfter + (int) $transaction->wallet_amount);
            $transaction->setAttribute('wallet_balance_after', $balanceAfter);
        }

        Log::info('SMART POS transaction committed', [
            'client_transaction_id' => $data['client_transaction_id'],
            'transaction_id' => $transaction->id,
            'stage' => $transaction->status === 'pending' ? 'gateway_pending' : 'transaction_committed',
        ]);

        return response()->json(['success' => true, 'message' => 'Transaksi berhasil.', 'data' => $transaction], 201);
    }

    public function sync(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'code' => 'OFFLINE_WALLET_NOT_ALLOWED',
            'message' => 'Pembayaran saldo tidak dapat disinkronkan dari mode offline.'], 409);
    }

    protected function resolveKasir(User $actingUser, ?int $kasirId): User
    {
        if ($actingUser->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMIN) && $kasirId) {
            return User::findOrFail($kasirId);
        }

        if ($kasirId && $kasirId !== $actingUser->id) {
            abort(403, 'Kasir tidak valid untuk pengguna ini.');
        }

        if (! $actingUser->hasAnyRole(UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value, UserRole::KASIR->value)) {
            abort(403, 'Role pengguna tidak dapat melakukan transaksi kasir.');
        }

        return $actingUser;
    }
}
