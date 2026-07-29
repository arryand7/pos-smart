<?php

namespace App\Services\Gate;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GatePhotoSyncService
{
    public function __construct(private GatePhotoUrlGuard $urlGuard) {}

    public function sync(User $user, mixed $photo): bool
    {
        if (! config('services.gate.sync_photo') || ! is_array($photo) || ! data_get($photo, 'available')) {
            return false;
        }
        $url = (string) data_get($photo, 'url');
        $checksum = (string) data_get($photo, 'checksum');
        if (! $url || ! $checksum || $checksum === $user->gate_photo_checksum) {
            return false;
        }

        $response = null;
        $maxRedirects = (int) config('services.gate.photo_max_redirects', 2);
        for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
            $resolvedIps = $this->urlGuard->validate($url);
            $parts = parse_url($url);
            $host = (string) ($parts['host'] ?? '');
            $port = (int) ($parts['port'] ?? 443);
            $response = Http::connectTimeout((int) config('services.gate.connect_timeout', 5))
                ->timeout((int) config('services.gate.timeout', 20))
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$resolvedIps[0]]],
                ])
                ->get($url);

            if (! $response->redirect()) {
                break;
            }

            $location = $response->header('Location');
            if (! $location || $redirects === $maxRedirects) {
                throw new RuntimeException('PHOTO_REDIRECT_REJECTED');
            }
            $url = $this->absoluteRedirect($url, $location);
        }

        if (! $response->successful()) {
            throw new RuntimeException('PHOTO_DOWNLOAD_FAILED');
        }
        $maxBytes = (int) config('services.gate.photo_max_bytes', 5242880);
        $contentLength = (int) $response->header('Content-Length');
        if ($contentLength > $maxBytes) {
            throw new RuntimeException('PHOTO_TOO_LARGE');
        }
        $body = $this->readBounded($response->toPsrResponse()->getBody(), $maxBytes);
        $imageInfo = @getimagesizefromstring($body);
        $declaredMime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! is_array($imageInfo) || ! isset($allowedMime[$imageInfo['mime'] ?? '']) || $declaredMime !== ($imageInfo['mime'] ?? null)) {
            throw new RuntimeException('PHOTO_INVALID_CONTENT');
        }
        $extension = $allowedMime[$imageInfo['mime']];
        $path = 'santris/gate-'.Str::uuid().'.'.$extension;
        if (! Storage::disk('public')->put($path, $body)) {
            throw new RuntimeException('PHOTO_STORAGE_FAILED');
        }
        $old = $user->santri?->photo_path;
        $user->santri?->update(['photo_path' => $path]);
        $user->update(['gate_photo_checksum' => $checksum]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return true;
    }

    private function readBounded($stream, int $maxBytes): string
    {
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(min(8192, $maxBytes + 1 - strlen($body)));
            if (strlen($body) > $maxBytes) {
                throw new RuntimeException('PHOTO_TOO_LARGE');
            }
        }

        return $body;
    }

    private function absoluteRedirect(string $currentUrl, string $location): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $parts = parse_url($currentUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/'.ltrim($location, '/');
    }
}
