<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'location'])
            ->where('is_active', true);

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->trim()) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        $sort = $request->string('sort')->toString();
        $query->when($sort, function ($q) use ($sort) {
            return match ($sort) {
                'az' => $q->orderBy('name'),
                'za' => $q->orderByDesc('name'),
                'price-low' => $q->orderBy('sale_price'),
                'price-high' => $q->orderByDesc('sale_price'),
                default => $q->orderByDesc('created_at'),
            };
        }, fn ($q) => $q->orderByDesc('created_at'));

        $viewType = $request->string('view', 'grid')->value();

        $products = $query->paginate(12)->withQueryString();
        $categories = ProductCategory::orderBy('name')->get();

        return view('catalog.index', [
            'products' => $products,
            'categories' => $categories,
            'viewType' => in_array($viewType, ['grid', 'list'], true) ? $viewType : 'grid',
            'activeCategory' => $category,
            'sort' => $sort,
            'search' => $search,
        ]);
    }
}
