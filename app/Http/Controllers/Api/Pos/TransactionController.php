<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\POS\PosService;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private readonly PosService $posService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $transactions = Transaction::query()
            ->with(['items', 'santri', 'kasir'])
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('processed_at')
            ->paginate($perPage);

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'string', Rule::in(['pos', 'topup'])],
            'channel' => ['nullable', 'string', 'max:30'],
            'location_id' => ['required', 'exists:locations,id'],
            'santri_id' => ['nullable', 'exists:santris,id'],
            'kasir_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:20'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.cash' => ['nullable', 'numeric', 'min:0'],
            'payments.wallet' => ['nullable', 'numeric', 'min:0'],
            'payments.gateway' => ['nullable', 'numeric', 'min:0'],
            'primary_payment_method' => ['nullable', 'string', 'max:30'],
            'processed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        $actingUser = $request->user();

        if (! $actingUser) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        $kasir = $this->resolveKasir($actingUser, $data['kasir_id'] ?? null);

        $transaction = $this->posService->createTransaction($data, $kasir);

        return response()->json($transaction->load('items'), 201);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.reference' => ['nullable', 'string'],
            'transactions.*.location_id' => ['required', 'exists:locations,id'],
            'transactions.*.santri_id' => ['nullable', 'exists:santris,id'],
            'transactions.*.payments' => ['nullable', 'array'],
            'transactions.*.payments.cash' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.payments.wallet' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.payments.gateway' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items' => ['required', 'array', 'min:1'],
            'transactions.*.items.*.product_id' => ['nullable', 'exists:products,id'],
            'transactions.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'transactions.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $actingUser = $request->user();

        if (! $actingUser) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        $kasir = $this->resolveKasir($actingUser, null);

        $synced = $this->posService->syncOfflinePayloads($data['transactions'], $kasir);

        return response()->json([
            'synced' => collect($synced)->map(fn ($transaction) => Arr::only($transaction->toArray(), [
                'id',
                'reference',
                'total_amount',
                'status',
                'processed_at',
            ])),
        ]);
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
