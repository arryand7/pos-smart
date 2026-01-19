<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    use ExportsTable;
    public function __construct()
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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->storeOptimizedPhoto($request->file('photo'));
        }
        unset($data['photo']);

        Product::create($data);

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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            if ($product->photo_path) {
                Storage::disk('public')->delete($product->photo_path);
            }
            $data['photo_path'] = $this->storeOptimizedPhoto($request->file('photo'));
        }
        unset($data['photo']);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('status', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Produk dihapus.');
    }

    protected function storeOptimizedPhoto(UploadedFile $file): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $file->store('products', 'public');
        }

        $path = $file->getPathname();
        $info = @getimagesize($path);

        if (! $info) {
            return $file->store('products', 'public');
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? null;

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };

        if (! $source) {
            return $file->store('products', 'public');
        }

        $maxDim = 800;
        $scale = min(1, $maxDim / max($width, $height));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        $encoded = $this->encodeJpeg($source, $width, $height, $targetW, $targetH, 82);
        $quality = 82;
        $maxBytes = 500 * 1024;

        while ($encoded && strlen($encoded) > $maxBytes && $quality > 50) {
            $quality -= 5;
            $encoded = $this->encodeJpeg($source, $width, $height, $targetW, $targetH, $quality);
        }

        $scaleDown = 0.9;
        while ($encoded && strlen($encoded) > $maxBytes && $targetW > 240 && $targetH > 240) {
            $targetW = max(1, (int) floor($targetW * $scaleDown));
            $targetH = max(1, (int) floor($targetH * $scaleDown));
            $encoded = $this->encodeJpeg($source, $width, $height, $targetW, $targetH, min($quality, 75));
        }

        imagedestroy($source);

        if (! $encoded) {
            return $file->store('products', 'public');
        }

        $filename = 'products/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($filename, $encoded);

        return $filename;
    }

    protected function encodeJpeg($source, int $srcW, int $srcH, int $destW, int $destH, int $quality): string
    {
        $canvas = imagecreatetruecolor($destW, $destH);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $destW, $destH, $srcW, $srcH);

        ob_start();
        imagejpeg($canvas, null, $quality);
        $data = ob_get_clean();
        imagedestroy($canvas);

        return $data ?: '';
    }
}
