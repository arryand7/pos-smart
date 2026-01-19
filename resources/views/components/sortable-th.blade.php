@props(['field', 'label', 'class' => ''])

@php
    $sort = request('sort');
    $direction = request('direction', 'asc');
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->except(['sort', 'direction', 'page']), [
        'sort' => $field,
        'direction' => $nextDirection,
    ]);
    $url = request()->url().'?'.http_build_query($query);
    $icon = $isActive ? ($direction === 'asc' ? '↑' : '↓') : '↕';
@endphp

<th class="{{ $class }}">
    <a href="{{ $url }}" class="inline-flex items-center gap-1">
        <span>{{ $label }}</span>
        <span class="text-slate-400 text-xs">{{ $icon }}</span>
    </a>
</th>
