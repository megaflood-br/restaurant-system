<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $middleware->alias([
            'waiter.cart' => \App\Http\Middleware\ShareWaiterCart::class,
            'integration.api' => \App\Http\Middleware\AuthenticateIntegrationApi::class,
            'role.admin' => \App\Http\Middleware\EnsureAdmin::class,
            'role.staff' => \App\Http\Middleware\EnsureStaff::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SkipNgrokBrowserWarning::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
