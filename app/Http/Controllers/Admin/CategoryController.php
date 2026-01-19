<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ExportsTable;
    public function __construct()
    {
        $this->authorizeResource(ProductCategory::class, 'category');
    }

    public function index(Request $request)
    {
        $query = ProductCategory::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'name', 'slug', 'is_restricted' => $builder->orderBy($sort, $direction),
                default => $builder->orderBy('name'),
            };
        }, fn ($builder) => $builder->orderBy('name'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (ProductCategory $category) {
                return [
                    $category->name,
                    $category->slug,
                    $category->is_restricted ? 'Terbatas' : 'Bebas',
                ];
            })->all();

            $headings = ['Kategori', 'Slug', 'Status'];

            return $this->exportTable($exportType, 'kategori', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return view('admin.categories.index', [
            'categories' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:product_categories,slug'],
            'description' => ['nullable', 'string'],
            'is_restricted' => ['nullable', 'boolean'],
        ]);

        ProductCategory::create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function edit(ProductCategory $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, ProductCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:product_categories,slug,'.$category->id],
            'description' => ['nullable', 'string'],
            'is_restricted' => ['nullable', 'boolean'],
        ]);

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ProductCategory $category)
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Kategori dihapus.');
    }
}
