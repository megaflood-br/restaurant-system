<?php

namespace App\Http\Middleware;

use App\Services\WaiterCartService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareWaiterCart
{
    public function handle(Request $request, Closure $next): Response
    {
        $cart = app(WaiterCartService::class);
        $context = $cart->all();

        View::share([
            'cartCount' => $cart->count(),
            'cartTotal' => $cart->total(),
            'comandaNumber' => $context['comanda_number'] ?? null,
        ]);

        return $next($request);
    }
}
