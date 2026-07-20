<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageOptimizer
{
    public function store(
        UploadedFile $file,
        string $directory,
        int $targetWidth,
        int $targetHeight,
        int $maxBytes,
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            throw ValidationException::withMessages([
                'photo' => 'Optimasi foto memerlukan ekstensi GD pada server.',
            ]);
        }

        $info = @getimagesize($file->getPathname());
        if (! $info) {
            throw ValidationException::withMessages(['photo' => 'File foto tidak dapat dibaca.']);
        }

        $source = $this->createSource($file->getPathname(), $info['mime'] ?? null);
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'Format foto tidak didukung.']);
        }

        $source = $this->orientJpeg($source, $file->getPathname(), $info['mime'] ?? null);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $renderWidth = max(1, (int) round($sourceWidth * $scale));
        $renderHeight = max(1, (int) round($sourceHeight * $scale));
        $destinationX = (int) floor(($targetWidth - $renderWidth) / 2);
        $destinationY = (int) floor(($targetHeight - $renderHeight) / 2);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);
        imagecopyresampled(
            $canvas,
            $source,
            $destinationX,
            $destinationY,
            0,
            0,
            $renderWidth,
            $renderHeight,
            $sourceWidth,
            $sourceHeight,
        );

        imagedestroy($source);

        $quality = 84;
        $encoded = $this->encodeJpeg($canvas, $quality);
        while (strlen($encoded) > $maxBytes && $quality > 25) {
            $quality -= 5;
            $encoded = $this->encodeJpeg($canvas, $quality);
        }
        imagedestroy($canvas);

        if ($encoded === '') {
            throw ValidationException::withMessages(['photo' => 'Foto gagal dioptimalkan.']);
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    private function createSource(string $path, ?string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };
    }

    private function orientJpeg(mixed $source, string $path, ?string $mime): mixed
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $source;
        }

        $rotated = imagerotate($source, $angle, imagecolorallocate($source, 255, 255, 255));
        if (! $rotated) {
            return $source;
        }

        imagedestroy($source);

        return $rotated;
    }

    private function encodeJpeg(mixed $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);
        $data = ob_get_clean();

        return $data ?: '';
    }
}
