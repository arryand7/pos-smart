<?php

namespace App\Services\Gate;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GateProvisioningClient
{
    public function users(): array
    {
        $response = $this->request()->get($this->url('/api/provisioning/users'));
        $this->assertSuccessful($response->status(), $response->json());
        $payload = $response->json();
        $users = data_get($payload, 'data', data_get($payload, 'users', $payload));
        if (! is_array($users) || ! array_is_list($users)) {
            throw new GateProvisioningException('GATE_INVALID_RESPONSE', 'Gate mengirim format daftar pengguna yang tidak valid.');
        }

        return $users;
    }

    public function report(array $items): void
    {
        $response = $this->request()->post($this->url('/api/provisioning/sync-results'), ['items' => $items]);
        $this->assertSuccessful($response->status(), $response->json());
    }

    public function checkConnection(): void
    {
        $this->users();
    }

    private function request(): PendingRequest
    {
        $id = config('services.gate.provisioning_client_id');
        $secret = config('services.gate.provisioning_client_secret');
        if (! $id || ! $secret || ! config('services.gate.url')) {
            throw new GateProvisioningException('GATE_CONFIGURATION_MISSING', 'Konfigurasi provisioning Gate belum lengkap.', 422);
        }

        return Http::acceptJson()->withHeaders(['X-Client-Id' => $id, 'X-Client-Secret' => $secret])
            ->connectTimeout((int) config('services.gate.connect_timeout', 5))
            ->timeout((int) config('services.gate.timeout', 20))
            ->retry(2, 500, throw: false);
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('services.gate.url'), '/');
        if (! str_starts_with($base, 'https://') && ! app()->environment('testing')) {
            throw new GateProvisioningException('GATE_CONFIGURATION_MISSING', 'Gate URL wajib menggunakan HTTPS.', 422);
        }

        return $base.$path;
    }

    private function assertSuccessful(int $status, mixed $payload): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }
        $code = match ($status) {
            401 => 'GATE_AUTHENTICATION_FAILED', 403 => 'GATE_ACCESS_DENIED', 429 => 'GATE_RATE_LIMITED', default => 'GATE_CONNECTION_FAILED'
        };
        throw new GateProvisioningException($code, 'Permintaan provisioning Gate gagal.', $status >= 400 && $status < 500 ? $status : 502);
    }
}
