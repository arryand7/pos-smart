<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SantriMediaController extends Controller
{
    public function __invoke(string $path): Response
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        $storagePath = 'santris/'.$path;

        if (! Storage::disk('public')->exists($storagePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($storagePath));
    }
}
