<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Exports\ExportsTable;
use App\Support\ImageOptimizer;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    use ExportsTable;

    public function __construct(private readonly ImageOptimizer $imageOptimizer)
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'location']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('location', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'category' => $builder->orderBy(
                    ProductCategory::select('name')->whereColumn('product_categories.id', 'products.category_id'),
                    $direction
                ),
                'location' => $builder->orderBy(
                    Location::select('name')->whereColumn('locations.id', 'products.location_id'),
                    $direction
                ),
                'name', 'sku', 'barcode', 'cost_price', 'sale_price', 'stock' => $builder->orderBy($sort, $direction),
                default => $builder->orderByDesc('created_at'),
            };
        }, fn ($builder) => $builder->orderByDesc('created_at'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (Product $product) {
                return [
                    $product->name,
                    $product->sku,
                    $product->category->name ?? '-',
                    $product->location->name ?? '-',
                    number_format($product->cost_price, 0, ',', '.'),
                    number_format($product->sale_price, 0, ',', '.'),
                    $product->stock.' '.$product->unit,
                ];
            })->all();

            $headings = ['Produk', 'SKU', 'Kategori', 'Lokasi', 'Harga Beli', 'Harga Jual', 'Stok'];

            return $this->exportTable($exportType, 'produk', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return view('admin.products.index', [
            'products' => $query->paginate($perPage)->withQueryString(),
            'locations' => Location::orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'filters' => $request->only(['search', 'location_id', 'category_id', 'per_page', 'sort', 'direction']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'locations' => Location::orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['required', 'string', 'max:32', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'location_id' => ['required', 'exists:locations,id'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $data['cost_price'] = Rupiah::from($data['cost_price'], 'cost_price');
        $data['sale_price'] = Rupiah::from($data['sale_price'], 'sale_price');

        $newPhotoPath = $request->hasFile('photo')
            ? $this->imageOptimizer->store($request->file('photo'), 'products', 640, 640, 300 * 1024)
            : null;
        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }
        unset($data['photo']);

        try {
            Product::create($data);
        } catch (\Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil dibuat.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'locations' => Location::orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['required', 'string', 'max:32', 'unique:products,sku,'.$product->id],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode,'.$product->id],
            'photo' => ['nullable', 'image', 'max:10240'],
            'location_id' => ['required', 'exists:locations,id'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $data['cost_price'] = Rupiah::from($data['cost_price'], 'cost_price');
        $data['sale_price'] = Rupiah::from($data['sale_price'], 'sale_price');

        $oldPhotoPath = $product->photo_path;
        $newPhotoPath = $request->hasFile('photo')
            ? $this->imageOptimizer->store($request->file('photo'), 'products', 640, 640, 300 * 1024)
            : null;
        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }
        unset($data['photo']);

        try {
            $product->update($data);
        } catch (\Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Produk dihapus.');
    }
}
