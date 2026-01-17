<?php

namespace App\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function getProviderKey(): string;

    /**
     * Bootstrap or validate provider config.
     */
    public function boot(array $config = []): void;

    /**
     * Create a payment charge or intent through the provider.
     */
    public function createCharge(Payment $payment, array $payload = []): Payment;

    /**
     * Handle webhook callbacks for asynchronous updates.
     *
     * Returns the updated payment instance.
     */
    public function handleWebhook(Request $request): Payment;

    /**
     * Check the latest transaction status from the provider.
     */
    public function checkTransaction(Payment $payment): Payment;

    /**
     * Determine whether the provider supports a specific capability.
     */
    public function supports(string $capability): bool;
}
