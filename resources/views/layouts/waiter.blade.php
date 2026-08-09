<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Garçom — {{ config('printing.restaurant_name', config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.ngrok-skip-warning')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-gray-900 antialiased pb-28">
    <header class="sticky top-0 z-40 bg-indigo-600 text-white shadow-lg">
        <div class="max-w-lg mx-auto px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-indigo-200 font-medium">Garçom</p>
                    <p class="font-semibold truncate">{{ Auth::user()->name }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('waiter.cart') }}" class="relative rounded-lg bg-indigo-500/80 hover:bg-indigo-500 px-3 py-2 text-xs font-semibold transition">
                        Pedido
                        @if (($cartCount ?? 0) > 0)
                            <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-indigo-700 text-[10px] font-bold">
                                {{ $cartCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('waiter.orders') }}" class="rounded-lg bg-indigo-500/80 hover:bg-indigo-500 px-3 py-2 text-xs font-semibold transition">
                        Pedidos
                    </a>
                    <a href="{{ route('waiter.comandas.index') }}" class="rounded-lg bg-indigo-500/80 hover:bg-indigo-500 px-3 py-2 text-xs font-semibold transition">
                        Comandas
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-indigo-800/60 hover:bg-indigo-800 px-3 py-2 text-xs font-semibold transition">
                            Sair
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2" x-data="{ comanda: '{{ $comandaNumber ?? '' }}' }">
                <form method="POST" action="{{ route('waiter.comanda') }}" class="flex items-center gap-2 flex-1">
                    @csrf
                    <label for="header_comanda" class="sr-only">Comanda</label>
                    <input type="number" name="comanda_number" id="header_comanda" min="1" max="999"
                        x-model="comanda"
                        value="{{ $comandaNumber ?? '' }}"
                        placeholder="Nº comanda"
                        class="w-24 rounded-lg border-0 bg-white/15 text-white placeholder-indigo-200 text-center font-bold text-lg focus:ring-2 focus:ring-white/50">
                    <button type="submit" class="rounded-lg bg-white text-indigo-700 px-4 py-2 text-sm font-bold hover:bg-indigo-50 transition">
                        Comanda
                    </button>
                </form>
                @if ($comandaNumber ?? null)
                    <span class="rounded-full bg-white/20 px-3 py-1.5 text-sm font-bold whitespace-nowrap">
                        #{{ str_pad((string) $comandaNumber, 3, '0', STR_PAD_LEFT) }}
                    </span>
                @else
                    <span class="text-xs text-indigo-200 whitespace-nowrap">Informe a comanda</span>
                @endif
            </div>
        </div>
    </header>

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

    @if (!($comandaNumber ?? null))
        <div class="max-w-lg mx-auto px-4 pt-3">
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                Informe o número da comanda antes de enviar o pedido.
            </div>
        </div>
    @endif

    <main class="max-w-lg mx-auto">
        @yield('content')
    </main>

    <div class="fixed bottom-0 inset-x-0 z-50 p-4 bg-gradient-to-t from-slate-50 via-slate-50/95 to-transparent pointer-events-none">
        <div class="max-w-lg mx-auto pointer-events-auto">
            @if (($cartCount ?? 0) > 0)
                <a href="{{ route('waiter.cart') }}"
                   class="flex items-center justify-between w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-4 shadow-lg shadow-indigo-600/30 transition active:scale-[0.98]">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-bold">{{ $cartCount }}</span>
                        <div>
                            <span class="font-semibold text-lg block leading-tight">Ver pedido</span>
                            <span class="text-xs text-indigo-200">Toque para enviar à cozinha</span>
                        </div>
                    </div>
                    <span class="font-bold text-lg">R$ {{ number_format($cartTotal ?? 0, 2, ',', '.') }}</span>
                </a>
            @else
                <div class="flex items-center justify-center w-full rounded-2xl bg-white border-2 border-dashed border-indigo-200 text-indigo-400 px-5 py-4 text-sm font-medium">
                    Adicione itens ao pedido
                </div>
            @endif
        </div>
    </div>
</body>
</html>
