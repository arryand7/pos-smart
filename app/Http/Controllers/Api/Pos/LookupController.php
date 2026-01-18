<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Santri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function locations(): JsonResponse
    {
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['data' => $locations]);
    }

    public function categories(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $categories]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $limit = $request->integer('limit', 200);

        $products = $query->limit($limit)->get([
            'id',
            'category_id',
            'location_id',
            'sku',
            'barcode',
            'photo_path',
            'name',
            'sale_price',
            'stock',
            'stock_alert',
        ]);

        return response()->json(['data' => $products]);
    }

    public function santris(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = Santri::query()->orderBy('name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('qr_code', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%");
            });
        }

        $santris = $query->limit(10)->get([
            'id',
            'nis',
            'qr_code',
            'name',
            'wallet_balance',
            'daily_limit',
            'weekly_limit',
            'monthly_limit',
            'is_wallet_locked',
        ]);

        return response()->json(['data' => $santris]);
    }
}
