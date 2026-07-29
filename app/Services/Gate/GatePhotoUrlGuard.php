<?php

namespace App\Services\Gate;

use RuntimeException;

class GatePhotoUrlGuard
{
    /**
     * @param  array<int, string>|null  $resolvedIps  Test-only deterministic DNS input.
     */
    public function validate(string $url, ?array $resolvedIps = null): array
    {
        $parts = parse_url($url);
        $allowedHost = strtolower((string) parse_url((string) config('services.gate.url'), PHP_URL_HOST));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || $host !== $allowedHost) {
            throw new RuntimeException('PHOTO_URL_HOST_NOT_ALLOWED');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('PHOTO_URL_CREDENTIALS_NOT_ALLOWED');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('PHOTO_URL_PRIVATE_ADDRESS');
        }

        $ips = $resolvedIps ?? $this->resolve($host);
        if ($ips === []) {
            throw new RuntimeException('PHOTO_URL_DNS_FAILED');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('PHOTO_URL_PRIVATE_ADDRESS');
            }
        }

        return $ips;
    }

    /** @return array<int, string> */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = gethostbynamel($host) ?: [];
        $aaaa = dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($aaaa as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
