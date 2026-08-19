<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                      <x-nav-link :href="route('comandas.index')" :active="request()->routeIs('comandas.*')">
                        Comandas
                    </x-nav-link>
                    <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">
                        Pedidos
                    </x-nav-link>
                    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                        Clientes
                    </x-nav-link>
                    <x-nav-dropdown
                        label="Cadastro"
                        :active="request()->routeIs('categories.*', 'products.*', 'users.*', 'delivery-areas.*', 'recipes.*')"
                    >
                        <x-nav-dropdown-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                            Categorias
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                            Cardápio
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('recipes.index')" :active="request()->routeIs('recipes.*')">
                            Fichas técnicas
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            Usuários
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('delivery-areas.index')" :active="request()->routeIs('delivery-areas.*')">
                            Região de entrega
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-dropdown
                        label="Estoque"
                        :active="request()->routeIs('ingredients.*', 'stock-categories.*')"
                    >
                        <x-nav-dropdown-link :href="route('ingredients.index')" :active="request()->routeIs('ingredients.index', 'ingredients.create', 'ingredients.edit', 'ingredients.movement')">
                            Itens de estoque
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('ingredients.prices')" :active="request()->routeIs('ingredients.prices')">
                            Preços de compra
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('stock-categories.index')" :active="request()->routeIs('stock-categories.*')">
                            Categorias de estoque
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-dropdown
                        label="Financeiro"
                        :active="request()->routeIs('financeiro.*', 'motoboy.*')"
                    >
                        <x-nav-dropdown-link :href="route('financeiro.index')" :active="request()->routeIs('financeiro.*')">
                            Fluxo de caixa
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('motoboy.index')" :active="request()->routeIs('motoboy.*')">
                            Motoboy
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*') || request()->routeIs('whatsapp.*')">
                        Configurações
                    </x-nav-link>
                    <x-nav-link :href="route('waiter.menu')" :active="request()->routeIs('waiter.*')">
                        Garçom
                    </x-nav-link>
                    <x-nav-link :href="route('public.menu')" :active="false" target="_blank">
                        Cardápio online ↗
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">
                Pedidos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('comandas.index')" :active="request()->routeIs('comandas.*')">
                Comandas
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                Clientes
            </x-responsive-nav-link>
            <div class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Cadastro</div>
            <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                Categorias
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                Cardápio
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('recipes.index')" :active="request()->routeIs('recipes.*')">
                Fichas técnicas
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                Usuários
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('delivery-areas.index')" :active="request()->routeIs('delivery-areas.*')">
                Região de entrega
            </x-responsive-nav-link>

            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Estoque</div>
            <x-responsive-nav-link :href="route('ingredients.index')" :active="request()->routeIs('ingredients.index', 'ingredients.create', 'ingredients.edit', 'ingredients.movement')">
                Itens de estoque
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('ingredients.prices')" :active="request()->routeIs('ingredients.prices')">
                Preços de compra
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('stock-categories.index')" :active="request()->routeIs('stock-categories.*')">
                Categorias de estoque
            </x-responsive-nav-link>

            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Financeiro</div>
            <x-responsive-nav-link :href="route('financeiro.index')" :active="request()->routeIs('financeiro.*')">
                Fluxo de caixa
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('motoboy.index')" :active="request()->routeIs('motoboy.*')">
                Motoboy
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*') || request()->routeIs('whatsapp.*')">
                Configurações
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('waiter.menu')" :active="request()->routeIs('waiter.*')">
                Garçom
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('public.menu')" :active="false">
                Cardápio online ↗
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
