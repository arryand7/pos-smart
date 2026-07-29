<?php

namespace App\Services\Gate;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GatePhotoSyncService
{
    public function sync(User $user, mixed $photo): bool
    {
        if (! config('services.gate.sync_photo') || ! is_array($photo) || ! data_get($photo, 'available')) {
            return false;
        }
        $url = (string) data_get($photo, 'url');
        $checksum = (string) data_get($photo, 'checksum');
        if (! $url || ! $checksum || $checksum === $user->gate_photo_checksum || ! str_starts_with($url, 'https://')) {
            return false;
        }
        $response = Http::connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('PHOTO_DOWNLOAD_FAILED');
        }
        $body = $response->body();
        if (strlen($body) > 5 * 1024 * 1024 || ! str_starts_with((string) $response->header('Content-Type'), 'image/') || @getimagesizefromstring($body) === false) {
            throw new RuntimeException('PHOTO_INVALID_CONTENT');
        }
        $extension = match (@getimagesizefromstring($body)['mime'] ?? '') {
            'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
        };
        $path = 'santris/gate-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $body);
        $old = $user->santri?->photo_path;
        $user->santri?->update(['photo_path' => $path]);
        $user->update(['gate_photo_checksum' => $checksum]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return true;
    }
}
