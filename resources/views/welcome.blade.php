@extends('layouts.app')

@section('title', 'SMART - Sabira Mart System')

@section('content')

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center gap-2 cursor-pointer" onclick="window.scrollTo(0,0)">
                    <div class="w-8 h-8 bg-[#007A5C] rounded-lg flex items-center justify-center text-white font-bold text-xl">S</div>
                    <span class="font-bold text-xl tracking-tight text-slate-800">SMART</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="#home" class="text-slate-600 hover:text-[#007A5C] font-medium transition-colors">Beranda</a>
                    <a href="#features" class="text-slate-600 hover:text-[#007A5C] font-medium transition-colors">Fitur</a>
                    <a href="#about" class="text-slate-600 hover:text-[#007A5C] font-medium transition-colors">Tentang Kami</a>
                </div>

                <!-- Login Button -->
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 rounded-full bg-[#007A5C] text-white font-semibold shadow-lg shadow-emerald-600/20 hover:bg-[#00624a] hover:shadow-emerald-600/30 transition-all transform hover:-translate-y-0.5">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('auth.login') }}" class="px-5 py-2.5 rounded-full bg-slate-900 text-white font-semibold shadow-lg hover:bg-slate-800 transition-all transform hover:-translate-y-0.5">
                            Masuk
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center max-w-3xl mx-auto animate-fade-up">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm font-semibold mb-6 border border-emerald-100">
                    <span class="w-2 h-2 rounded-full bg-[#007A5C]"></span>
                    Sistem Manajemen Ponpes Modern
                </div>
                <h1 class="text-5xl lg:text-7xl font-extrabold tracking-tight text-slate-900 mb-8 leading-tight">
                    Kelola Ekonomi Santri dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#007A5C] to-emerald-400">SMART</span>
                </h1>
                <p class="text-xl text-slate-600 mb-10 leading-relaxed max-w-2xl mx-auto">
                    Sistem Manajemen Akuntansi & Resi Transaksi yang mengintegrasikan uang saku digital, kantin cashless, dan laporan keuangan real-time.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#about" class="px-8 py-4 rounded-full bg-white text-slate-900 font-bold border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all shadow-sm">
                        Pelajari Lebih Lanjut
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-slate-900 mb-4">Fitur Unggulan</h2>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">Solusi lengkap untuk ekosistem ekonomi pesantren yang transparan dan efisien.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 stagger" style="--stagger: 0ms;">
                <!-- Feature 1 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/60 transition-all group" style="--stagger: 0ms;">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Cashless Payment</h3>
                    <p class="text-slate-600 leading-relaxed">Transaksi belanja santri menggunakan kartu digital. Aman, cepat, dan tercatat otomatis.</p>
                </div>

                <!-- Feature 2 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/60 transition-all group" style="--stagger: 120ms;">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Real-time Analytics</h3>
                    <p class="text-slate-600 leading-relaxed">Pantau arus kas, omzet penjualan, dan performa kantin dalam grafik yang interaktif.</p>
                </div>

                <!-- Feature 3 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/60 transition-all group" style="--stagger: 240ms;">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Portal Wali Santri</h3>
                    <p class="text-slate-600 leading-relaxed">Orang tua dapat memantau saldo, limit belanja, dan riwayat transaksi anak dari rumah.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                <div class="mb-12 lg:mb-0">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-sm font-semibold mb-6">
                        Tentang Kami
                    </div>
                    <h2 class="text-4xl font-bold text-slate-900 mb-6">Meningkatkan Transparansi & Efisiensi Pesantren</h2>
                    <p class="text-lg text-slate-600 mb-6 leading-relaxed">
                        Pesantren Sabira Mart menghadirkan inovasi teknologi untuk mendukung kemandirian ekonomi. Melalui SMART, kami mengurangi risiko kehilangan uang tunai, mencegah penyalahgunaan uang saku, dan memberikan ketenangan pikiran bagi wali santri.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm">✓</span>
                            <span class="text-slate-700 font-medium">Monitoring limit belanja harian</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm">✓</span>
                            <span class="text-slate-700 font-medium">Blokir kategori produk per santri</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm">✓</span>
                            <span class="text-slate-700 font-medium">Laporan keuangan standar akuntansi</span>
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-[#007A5C] rounded-2xl transform rotate-3 opacity-10"></div>
                    <div class="relative bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="w-12 h-12 bg-slate-900 rounded-full flex items-center justify-center text-white font-bold">SM</div>
                            <div>
                                <h4 class="font-bold text-slate-900">Sabira Mart</h4>
                                <p class="text-slate-500 text-sm">Official System</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="h-2 bg-slate-100 rounded-full w-3/4"></div>
                            <div class="h-2 bg-slate-100 rounded-full w-full"></div>
                            <div class="h-2 bg-slate-100 rounded-full w-5/6"></div>
                            <div class="h-2 bg-slate-100 rounded-full w-4/5"></div>
                        </div>
                        <div class="mt-8 pt-8 border-t border-slate-100 flex justify-between items-center">
                            <div>
                                <p class="text-sm text-slate-500">Total Transaksi Hari Ini</p>
                                <p class="text-2xl font-bold text-[#007A5C]">1.240+</p>
                            </div>
                            <a href="#" class="text-[#007A5C] font-semibold text-sm hover:underline">Lihat Statistik →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div class="col-span-1 md:col-span-2">
                    <h3 class="text-white text-xl font-bold mb-4">SMART</h3>
                    <p class="max-w-sm leading-relaxed">
                        Sistem Manajemen Akuntansi & Resi Transaksi untuk Pesantren Sabira Mart. Mendorong digitalisasi ekonomi pesantren.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Menu</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="hover:text-emerald-400 transition-colors">Beranda</a></li>
                        <li><a href="#features" class="hover:text-emerald-400 transition-colors">Fitur</a></li>
                        <li><a href="#about" class="hover:text-emerald-400 transition-colors">Tentang Kami</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Kontak</h4>
                    <ul class="space-y-2">
                        <li>info@sabiramart.com</li>
                        <li>(021) 555-0123</li>
                        <li>Jl. Pesantren No. 1</li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-800 text-center text-sm">
                &copy; 2026 Sabira Mart. All rights reserved.
            </div>
        </div>
    </footer>

@endsection
