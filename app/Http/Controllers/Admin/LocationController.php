<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ExportsTable;
    public function __construct()
    {
        $this->authorizeResource(Location::class, 'location');
    }

    public function index(Request $request)
    {
        $query = Location::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'name', 'code', 'type', 'manager_name', 'is_active' => $builder->orderBy($sort, $direction),
                default => $builder->orderBy('name'),
            };
        }, fn ($builder) => $builder->orderBy('name'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (Location $location) {
                return [
                    $location->name,
                    $location->code,
                    $location->type ?? '-',
                    $location->manager_name ?? '-',
                    $location->phone ?? '-',
                    $location->is_active ? 'Aktif' : 'Nonaktif',
                ];
            })->all();

            $headings = ['Lokasi', 'Kode', 'Tipe', 'Penanggung Jawab', 'Telepon', 'Status'];

            return $this->exportTable($exportType, 'lokasi', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return view('admin.locations.index', [
            'locations' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', 'unique:locations,code'],
            'type' => ['nullable', 'string', 'max:40'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Location::create($data);

        return redirect()->route('admin.locations.index')->with('status', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(Location $location)
    {
        return view('admin.locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', 'unique:locations,code,'.$location->id],
            'type' => ['nullable', 'string', 'max:40'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location->update($data);

        return redirect()->route('admin.locations.index')->with('status', 'Lokasi berhasil diperbarui.');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('admin.locations.index')->with('status', 'Lokasi dihapus.');
    }
}
