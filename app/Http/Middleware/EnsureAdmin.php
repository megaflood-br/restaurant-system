<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            if ($user?->isWaiter()) {
                return redirect()->route('waiter.menu')
                    ->with('error', 'Acesso restrito ao painel administrativo.');
            }

            abort(403);
        }

        return $next($request);
    }
}
