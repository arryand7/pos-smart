<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentProviderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentProvider> */
    protected array $providers = [];

    /** @var array<string, array> */
    protected array $runtimeConfig = [];

    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    public function registerProvider(PaymentProvider $provider): void
    {
        $this->providers[$provider->getProviderKey()] = $provider;
    }

    public function provider(?string $providerKey = null): PaymentProvider
    {
        $key = $providerKey ?: $this->getDefaultProviderKey();

        if (! array_key_exists($key, $this->providers)) {
            $class = config("smart.payments.provider_map.$key");

            if (! $class || ! class_exists($class)) {
                throw new InvalidArgumentException("Payment provider [$key] is not registered.");
            }

            $this->registerProvider(app($class));
        }

        $provider = $this->providers[$key];
        $provider->boot($this->loadProviderConfig($key));

        return $provider;
    }

    public function createCharge(Payment $payment, array $payload = []): Payment
    {
        return $this->provider($payment->provider)->createCharge($payment, $payload);
    }

    public function handleWebhook(string $providerKey, Request $request): Payment
    {
        $provider = $this->provider($providerKey);

        return $provider->handleWebhook($request);
    }

    public function checkTransaction(Payment $payment): Payment
    {
        return $this->provider($payment->provider)->checkTransaction($payment);
    }

    public function withRuntimeConfig(string $providerKey, array $config): void
    {
        $this->runtimeConfig[$providerKey] = $config;
    }

    protected function getDefaultProviderKey(): string
    {
        return config('smart.payments.default_provider', 'ipaymu');
    }

    protected function loadProviderConfig(string $providerKey): array
    {
        $config = config("smart.payments.providers.$providerKey", []);

        $dbConfig = PaymentProviderConfig::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->first();

        if ($dbConfig) {
            $config = array_merge($config, $dbConfig->config ?? []);
        }

        if (array_key_exists($providerKey, $this->runtimeConfig)) {
            $config = array_merge($config, Arr::wrap($this->runtimeConfig[$providerKey]));
        }

        return $config;
    }
}
