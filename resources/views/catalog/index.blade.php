<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produk SMART</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
</head>
<body class="app-body">
    <section class="relative overflow-hidden">
        <div class="absolute -top-16 right-0 w-64 h-64 bg-emerald-400/10 blur-3xl rounded-full"></div>
        <div class="absolute -bottom-16 left-0 w-64 h-64 bg-cyan-400/10 blur-3xl rounded-full"></div>
        <div class="max-w-6xl mx-auto px-6 py-16 text-center">
            <div class="chip justify-center mx-auto w-fit">Katalog Produk</div>
            <h1 class="text-3xl md:text-5xl font-bold text-slate-900 mt-4">Jelajahi Produk SMART</h1>
            <p class="text-slate-500 mt-3">
                Pilih kebutuhan santri dengan mudah, cari dan sortir sesuai kategori favoritmu.
            </p>
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-6 -mt-10 pb-16 space-y-6">
        <form class="card grid gap-4 md:grid-cols-4 items-end" method="GET" action="{{ route('catalog.index') }}">
            <label class="form-label">
                Pencarian
                <input class="form-input" type="text" name="search" placeholder="Cari nama / SKU" value="{{ $search }}">
            </label>
            <label class="form-label">
                Kategori
                <select class="form-select" name="category">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" @selected($activeCategory === $category->slug)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">
                Urutkan
                <select class="form-select" name="sort">
                    <option value="">Terbaru</option>
                    <option value="az" @selected($sort === 'az')>Nama A-Z</option>
                    <option value="za" @selected($sort === 'za')>Nama Z-A</option>
                    <option value="price-low" @selected($sort === 'price-low')>Harga Termurah</option>
                    <option value="price-high" @selected($sort === 'price-high')>Harga Termahal</option>
                </select>
            </label>
            <button type="submit" class="btn btn-primary w-full">Terapkan</button>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm text-slate-500">Tampilan:</span>
            @php $query = request()->all(); @endphp
            <div class="flex gap-2">
                <a href="{{ route('catalog.index', array_merge($query, ['view' => 'grid'])) }}"
                   class="btn btn-outline btn-sm {{ $viewType === 'grid' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : '' }}">
                    Galeri
                </a>
                <a href="{{ route('catalog.index', array_merge($query, ['view' => 'list'])) }}"
                   class="btn btn-outline btn-sm {{ $viewType === 'list' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : '' }}">
                    Detail
                </a>
            </div>
        </div>

        @if($products->count())
            @if($viewType === 'grid')
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($products as $product)
                        <div class="card hover:shadow-md transition">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-success">{{ $product->category?->name ?? 'Tanpa Kategori' }}</span>
                                <span class="text-xs text-slate-400">SKU: {{ $product->sku }}</span>
                            </div>
                            <h3 class="text-lg font-semibold text-slate-800">{{ $product->name }}</h3>
                            <p class="text-sm text-slate-500">Lokasi: {{ $product->location->name ?? '-' }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-xl font-bold text-slate-900">Rp{{ number_format($product->sale_price, 0, ',', '.') }}</p>
                                <span class="badge badge-info">Stok {{ $product->stock }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card overflow-x-auto p-0">
                    <div class="table-scroll">
                        <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Lokasi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($products as $product)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-800">{{ $product->name }}</div>
                                    <div class="text-xs text-slate-500">SKU: {{ $product->sku }}</div>
                                </td>
                                <td>{{ $product->category?->name ?? '-' }}</td>
                                <td>Rp{{ number_format($product->sale_price, 0, ',', '.') }}</td>
                                <td>{{ $product->stock }}</td>
                                <td>{{ $product->location->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="card text-center text-slate-500">
                Produk tidak ditemukan. Silakan ubah filter pencarian.
            </div>
        @endif

        <div class="mt-8 flex justify-center">
            {{ $products->links() }}
        </div>
    </div>
</body>
</html>
