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
    ->withMiddleware(function (Middleware $middleware) {
        // The gateway callback is a server-to-server POST with no session or
        // token to present; the shared key on the route authenticates it.
        $middleware->validateCsrfTokens(except: [
            'api/whatsapp/ack',
        ]);

        $middleware->alias([
            'idempotency' => App\Http\Middleware\EnsureIdempotency::class,
            'whatsapp.gateway' => App\Http\Middleware\EnsureWhatsappGatewayKey::class,
            // Task API tokens are scoped by ability, so the checks have to be
            // reachable as route middleware.
            'abilities' => Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        // The admin area lives on the panel subdomain behind its own guard, so
        // a guest caught there belongs at the admin login, not the public one.
        $middleware->redirectGuestsTo(fn (Request $request) => str_starts_with($request->getHost(), 'panel.')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
