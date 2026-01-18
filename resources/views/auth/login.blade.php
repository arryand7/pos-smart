<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SMART Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
</head>
<body class="app-body">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="soft-panel p-8 relative overflow-hidden">
            <div class="absolute -top-16 -right-16 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl"></div>
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold">S</div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800">SMART POS</h1>
                    <p class="text-xs text-slate-500">Masuk untuk mengakses kasir</p>
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success mb-4">{{ session('status') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger mb-4">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('auth.login.submit') }}" class="form-stack">
                @csrf
                <label class="form-label">
                    Email
                    <input class="form-input" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </label>
                <label class="form-label">
                    Password
                    <input class="form-input" name="password" type="password" required>
                </label>
                <button type="submit" class="btn btn-primary w-full">Masuk</button>
            </form>

            <div class="flex items-center gap-3 text-xs text-slate-400 my-5">
                <span class="flex-1 h-px bg-slate-200"></span>
                atau
                <span class="flex-1 h-px bg-slate-200"></span>
            </div>
            <a class="btn btn-outline w-full" href="{{ route('sso.login') }}">Masuk dengan Sabira Connect</a>

            <p class="text-xs text-slate-500 text-center mt-6">Gunakan akun kasir atau admin untuk mengakses POS.</p>
        </div>
        <div class="text-center text-xs text-slate-400 mt-6">
            © 2026 Ryand Arifriantoni. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
