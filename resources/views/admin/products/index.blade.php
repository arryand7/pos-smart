@extends('layouts.admin')

@section('title', 'Master Data Produk')
@section('subtitle', 'Kelola inventaris, harga, dan stok produk.')

@section('actions')
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Produk
    </a>
@endsection

@section('content')
<div class="admin-card">
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Lokasi</th>
                <th>Harga Beli</th>
                <th>Harga Jual</th>
                <th>Stok</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center text-[10px] text-slate-400">
                            @if(!empty($product->photo_url))
                                <img src="{{ $product->photo_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            @else
                                NO IMG
                            @endif
                        </div>
                        <div>
                            <div class="font-medium text-slate-800">{{ $product->name }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ $product->sku }}</div>
                        </div>
                    </div>
                </td>
                <td class="text-sm">
                    {{ $product->category->name ?? '-' }}
                </td>
                <td class="text-sm">
                    {{ $product->location->name ?? '-' }}
                </td>
                <td class="text-sm text-slate-500">
                    Rp{{ number_format($product->cost_price, 0, ',', '.') }}
                </td>
                <td class="font-medium text-slate-800">
                    Rp{{ number_format($product->sale_price, 0, ',', '.') }}
                </td>
                <td>
                    @if($product->stock <= $product->stock_alert)
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-700">
                            {{ $product->stock }} {{ $product->unit }} (Low)
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700">
                            {{ $product->stock }} {{ $product->unit }}
                        </span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.products.edit', $product) }}" class="p-1 text-slate-400 hover:text-amber-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>
@endsection
