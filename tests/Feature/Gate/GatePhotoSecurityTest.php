<?php

namespace Tests\Feature\Gate;

use App\Models\Santri;
use App\Models\User;
use App\Services\Gate\GatePhotoSyncService;
use App\Services\Gate\GatePhotoUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GatePhotoSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_hostname_with_public_dns_is_allowed(): void
    {
        config(['services.gate.url' => 'https://gate.example.test']);
        app(GatePhotoUrlGuard::class)->validate('https://gate.example.test/photo/1', ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('rejectedUrls')]
    public function test_foreign_localhost_and_private_photo_urls_are_rejected(string $gateUrl, string $url, ?array $ips): void
    {
        config(['services.gate.url' => $gateUrl]);
        $this->expectException(RuntimeException::class);
        app(GatePhotoUrlGuard::class)->validate($url, $ips);
    }

    public static function rejectedUrls(): array
    {
        return [
            'foreign host' => ['https://gate.example.test', 'https://evil.example.test/photo', ['93.184.216.34']],
            'localhost' => ['https://localhost', 'https://localhost/photo', ['127.0.0.1']],
            'private ipv4' => ['https://10.0.0.1', 'https://10.0.0.1/photo', ['10.0.0.1']],
            'loopback ipv6' => ['https://[::1]', 'https://[::1]/photo', ['::1']],
            'metadata' => ['https://169.254.169.254', 'https://169.254.169.254/latest/meta-data', ['169.254.169.254']],
            'url credentials' => ['https://gate.example.test', 'https://user:pass@gate.example.test/photo', ['93.184.216.34']],
        ];
    }

    public function test_redirect_to_private_ip_is_rejected(): void
    {
        [$user] = $this->santriUser();
        config(['services.gate.url' => 'https://93.184.216.34', 'services.gate.sync_photo' => true]);
        Http::fake(['https://93.184.216.34/photo' => Http::response('', 302, ['Location' => 'https://127.0.0.1/private'])]);
        $this->expectException(RuntimeException::class);
        app(GatePhotoSyncService::class)->sync($user, ['available' => true, 'url' => 'https://93.184.216.34/photo', 'checksum' => 'new']);
    }

    public function test_non_image_and_oversized_downloads_are_rejected(): void
    {
        [$user] = $this->santriUser();
        config(['services.gate.url' => 'https://93.184.216.34', 'services.gate.sync_photo' => true, 'services.gate.photo_max_bytes' => 10]);

        Http::fake(['https://93.184.216.34/not-image' => Http::response('plain text', 200, ['Content-Type' => 'text/plain'])]);
        try {
            app(GatePhotoSyncService::class)->sync($user, ['available' => true, 'url' => 'https://93.184.216.34/not-image', 'checksum' => 'one']);
            $this->fail('Non-image content was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('PHOTO_INVALID_CONTENT', $exception->getMessage());
        }

        Http::fake(['https://93.184.216.34/large' => Http::response(str_repeat('x', 11), 200, ['Content-Type' => 'image/png'])]);
        $this->expectExceptionMessage('PHOTO_TOO_LARGE');
        app(GatePhotoSyncService::class)->sync($user, ['available' => true, 'url' => 'https://93.184.216.34/large', 'checksum' => 'two']);
    }

    private function santriUser(): array
    {
        $user = User::factory()->create(['role' => 'santri']);
        $santri = Santri::create(['user_id' => $user->id, 'nis' => 'PHOTO-'.$user->id, 'name' => $user->name]);

        return [$user, $santri];
    }
}
