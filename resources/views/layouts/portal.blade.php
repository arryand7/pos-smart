<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SMART Portal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
    <style>
        body { padding-bottom: 80px; }
        /* Hide scrollbar for horizontal scrolling areas */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="app-body">

    <!-- Header -->
    <header class="portal-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-[#007A5C] rounded-lg flex items-center justify-center text-white font-bold">S</div>
            <h1 class="font-bold text-lg text-slate-800">SMART Portal</h1>
        </div>
        <div class="flex items-center gap-3">
             <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="text-slate-500 hover:text-red-600 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </header>

    <!-- Content -->
    <main class="pt-20 px-4 max-w-md mx-auto min-h-screen">
        @if(session('status'))
            <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium border border-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm font-medium border border-red-100">
                {{ session('error') }}
            </div>
        @endif

        @include('partials.portal-switcher')

        @yield('content')
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav max-w-md mx-auto">
        <a href="#" class="nav-item {{ request()->routeIs('portal.wali') || request()->routeIs('portal.santri') ? 'active' : '' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Beranda</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>Profil</span>
        </a>
    </nav>

</body>
</html>
