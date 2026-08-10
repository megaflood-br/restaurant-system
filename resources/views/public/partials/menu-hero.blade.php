@php($menu = \App\Support\DigitalMenu::data())

<header class="relative bg-gray-50">
    {{-- Capa --}}
    <div class="relative h-44 sm:h-52 w-full overflow-hidden menu-gradient">
        @if ($menu['cover_url'])
            <img src="{{ $menu['cover_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-black/20"></div>
        @endif
    </div>

    {{-- Card principal --}}
    <div class="relative -mt-10 px-4 pb-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-4 pt-12 pb-4 text-center">
            {{-- Logo --}}
            <div class="absolute left-1/2 -translate-x-1/2 -top-8">
                @if ($menu['logo_url'])
                    <img src="{{ $menu['logo_url'] }}" alt="{{ $menu['display_name'] }}"
                        class="h-20 w-20 rounded-full object-cover border-4 border-white shadow-md bg-white">
                @else
                    <div class="h-20 w-20 rounded-full border-4 border-white shadow-md menu-bg text-white flex items-center justify-center text-2xl font-bold">
                        {{ mb_substr($menu['display_name'], 0, 1) }}
                    </div>
                @endif
            </div>

            <h1 class="text-xl font-bold text-gray-900">{{ $menu['display_name'] }}</h1>

            @if ($menu['city'] || $menu['state'])
                <p class="mt-1 text-sm text-gray-500 flex items-center justify-center gap-1 flex-wrap">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ trim($menu['city'].($menu['state'] ? ' - '.$menu['state'] : '')) }}</span>
                    @if ($menu['more_info'])
                        <span class="text-gray-300">•</span>
                        <button type="button" @click="infoOpen = true" class="menu-text font-medium">Mais informações</button>
                    @endif
                </p>
            @endif

            <p class="mt-2 text-sm font-medium {{ $menu['is_open'] ? 'text-green-600' : 'text-red-600' }}">
                {{ $menu['status_label'] }} <span class="text-gray-400 font-normal">•</span> {{ $menu['status_detail'] }}
            </p>

            @isset($comandaNumber)
                @if($comandaNumber)
                    <span class="inline-flex mt-2 items-center rounded-full menu-bg-soft px-3 py-1 text-xs font-semibold menu-text-soft">
                        Comanda {{ str_pad((string) $comandaNumber, 3, '0', STR_PAD_LEFT) }}
                    </span>
                @endif
            @endisset
        </div>

        {{-- Entrega --}}
        @if ($menu['address_line'])
            <a href="{{ route('public.checkout') }}"
                class="mt-3 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm menu-border-soft transition">
                <div class="shrink-0 text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l3-2 3 2 4-3 4 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 10h6"/></svg>
                </div>
                <div class="flex-1 min-w-0 text-left">
                    <p class="font-semibold text-gray-900 truncate">{{ $menu['address_line'] }}</p>
                    <p class="text-sm text-gray-500">Entrega em {{ $menu['delivery_minutes'] }} min / R$ {{ number_format((float) $menu['delivery_fee'], 2, ',', '.') }}</p>
                </div>
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endif

        {{-- Fidelidade --}}
        @if ($menu['loyalty_enabled'] && ($menu['loyalty_title'] || $menu['loyalty_text']))
            <div class="mt-3 rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm">
                @if ($menu['loyalty_title'])
                    <p class="font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a4 4 0 00-4-4H5a2 2 0 00-2 2v6a2 2 0 002 2h2m8-8V6a4 4 0 014-4h1a2 2 0 012 2v6a2 2 0 01-2 2h-2"/></svg>
                        {{ $menu['loyalty_title'] }}
                    </p>
                @endif
                @if ($menu['loyalty_text'])
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $menu['loyalty_text'] }}</p>
                @endif
            </div>
        @endif
    </div>

    @if ($menu['more_info'])
        <div x-show="infoOpen" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-4 bg-black/50"
            @keydown.escape.window="infoOpen = false">
            <div @click.outside="infoOpen = false" class="bg-white rounded-2xl w-full max-w-sm p-5 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Mais informações</h3>
                <div class="text-sm text-gray-600 whitespace-pre-line">{{ $menu['more_info'] }}</div>
                <button type="button" @click="infoOpen = false" class="mt-4 w-full rounded-xl menu-bg text-white font-semibold py-3">Fechar</button>
            </div>
        </div>
    @endif
</header>
