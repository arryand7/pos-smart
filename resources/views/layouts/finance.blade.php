<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SMART Finance')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
</head>
<body class="app-body">
<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="sidebar-panel w-64 bg-[#0f172a] text-white flex-shrink-0 flex flex-col transition-all duration-300 z-20">
        <div class="h-16 flex items-center px-6 border-b border-slate-700/50">
            <h1 class="text-xl font-bold bg-gradient-to-r from-[#007A5C] to-[#22c55e] bg-clip-text text-transparent">SMART Finance</h1>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 custom-scrollbar">
            <a href="{{ route('dashboard.finance') }}" class="sidebar-link {{ request()->routeIs('dashboard.finance') ? 'active' : '' }}">
                <span>📊</span> Dashboard
            </a>

            <details class="sidebar-group" @if(request()->routeIs('reports.profit-loss', 'reports.balance-sheet', 'reports.cash-flow')) open @endif>
                <summary class="sidebar-summary">
                    <span>Laporan Keuangan</span>
                    <svg class="sidebar-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </summary>
                <div class="sidebar-links">
                    <a href="{{ route('reports.profit-loss') }}" class="sidebar-link {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}">
                        <span>📉</span> Laba Rugi
                    </a>
                    <a href="{{ route('reports.balance-sheet') }}" class="sidebar-link {{ request()->routeIs('reports.balance-sheet') ? 'active' : '' }}">
                        <span>⚖️</span> Neraca
                    </a>
                    <a href="{{ route('reports.cash-flow') }}" class="sidebar-link {{ request()->routeIs('reports.cash-flow') ? 'active' : '' }}">
                        <span>💸</span> Arus Kas
                    </a>
                </div>
            </details>

            <details class="sidebar-group" @if(request()->routeIs('admin.settings.accounting*')) open @endif>
                <summary class="sidebar-summary">
                    <span>Pengaturan</span>
                    <svg class="sidebar-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </summary>
                <div class="sidebar-links">
                    <a href="{{ route('admin.settings.accounting') }}" class="sidebar-link {{ request()->routeIs('admin.settings.accounting*') ? 'active' : '' }}">
                        <span>📊</span> Akuntansi
                    </a>
                </div>
            </details>

            <details class="sidebar-group" @if(request()->routeIs('admin.products.*')) open @endif>
                <summary class="sidebar-summary">
                    <span>Inventaris</span>
                    <svg class="sidebar-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </summary>
                <div class="sidebar-links">
                    <a href="/admin/products" class="sidebar-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                        <span>📦</span> Data Produk
                    </a>
                </div>
            </details>
        </nav>

        <div class="p-4 border-t border-slate-700/50">
            <a href="/produk" target="_blank" class="flex items-center gap-2 text-sm text-slate-400 hover:text-white mb-3 px-2">
                <span>🌐</span> Katalog Publik
            </a>
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 text-sm text-slate-400 hover:text-white mb-3 px-2 {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                <span>👤</span> Profil
            </a>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <span>🚪</span> Keluar
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-[#f8fafc]">
        <!-- Mobile Header -->
        <header class="md:hidden h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 z-10">
            <h1 class="font-bold text-[#007A5C]">SMART Finance</h1>
            <button class="p-2 text-slate-500 rounded-lg hover:bg-slate-100" data-sidebar-toggle>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </header>

        <div class="sidebar-overlay md:hidden" data-sidebar-overlay></div>

        <!-- Page Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 md:p-8 custom-scrollbar relative">
            <!-- Breadcrumbs / Title -->
            <div class="mb-6 flex flex-wrap justify-between items-end gap-4">
                <div>
                    <h2 class="page-title">@yield('title', 'Dashboard')</h2>
                    @hasSection('subtitle')
                        <p class="page-subtitle">@yield('subtitle')</p>
                    @endif
                </div>
                <div>
                    @yield('actions')
                </div>
            </div>

            <div class="space-y-6">
                @if(session('status'))
                    <div class="alert alert-success flex items-center gap-3 shadow-sm" role="alert">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger flex items-center gap-3 shadow-sm" role="alert">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @include('partials.portal-switcher')

                @yield('content')
            </div>
        </main>
    </div>
</div>
</body>
</html>
