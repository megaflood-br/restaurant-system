<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\WaiterCartService;
use App\Support\AppSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WaiterCartService::class);
    }

    public function boot(): void
    {
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');
        }

        AppSettings::loadIntoConfig();

        View::composer('layouts.menu', function ($view) {
            $cart = app(CartService::class);
            $context = $cart->all();

            $view->with([
                'cartCount' => $cart->count(),
                'cartTotal' => $cart->total(),
                'comandaNumber' => $context['comanda_number'] ?? null,
            ]);
        });
    }

    private function shouldForceHttps(): bool
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            return true;
        }

        return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    }
}
