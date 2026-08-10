<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($menuTitle = config('digital_menu.display_name', config('printing.restaurant_name', config('app.name'))))
    <title>{{ $menuTitle }} — Cardápio</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @include('public.partials.menu-theme-styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.ngrok-skip-warning')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased pb-24" x-data="{ infoOpen: false }">
    @hasSection('hero')
        @yield('hero')
    @else
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-100 shadow-sm">
            <div class="max-w-lg mx-auto px-4 py-4 flex items-center gap-3">
                <a href="{{ route('public.menu') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-lg font-bold text-gray-900">{{ $menuTitle }}</h1>
                    <p class="text-xs text-gray-500">@yield('page-title', 'Cardápio online')</p>
                </div>
            </div>
        </header>
    @endif

    @if (session('success'))
        <div class="max-w-lg mx-auto px-4 pt-3">
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        </div>
    @endif

    @if (session('info'))
        <div class="max-w-lg mx-auto px-4 pt-3">
            <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>
        </div>
    @endif

    <main class="max-w-lg mx-auto">
        @yield('content')
    </main>

    @if (($cartCount ?? 0) > 0)
        <div class="fixed bottom-0 inset-x-0 z-50 p-4 bg-gradient-to-t from-gray-50 via-gray-50/95 to-transparent">
            <div class="max-w-lg mx-auto">
                <a href="{{ route('public.cart') }}"
                   class="flex items-center justify-between w-full rounded-2xl bg-[var(--menu-500)] hover:bg-[var(--menu-600)] text-white px-5 py-4 shadow-lg transition">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold">{{ $cartCount }}</span>
                        <span class="font-semibold">Ver carrinho</span>
                    </div>
                    <span class="font-bold">R$ {{ number_format($cartTotal ?? 0, 2, ',', '.') }}</span>
                </a>
            </div>
        </div>
    @endif
</body>
</html>
