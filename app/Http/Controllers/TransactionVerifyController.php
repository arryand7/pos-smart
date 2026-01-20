<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionVerifyController extends Controller
{
    public function __invoke(Request $request, string $token): View
    {
        $transaction = Transaction::query()
            ->where('metadata->verification_token', $token)
            ->firstOrFail();

        $transaction->load(['location', 'kasir', 'santri', 'items', 'payments']);

        return view('verify.transaction', compact('transaction'));
    }
}
