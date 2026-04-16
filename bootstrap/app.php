<?php

use App\Http\Middleware\EnforceTokenIpBinding;
use App\Http\Middleware\EnsureAccountOwnership;
use App\Http\Middleware\EnsureServiceConnected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            EnforceTokenIpBinding::class,
        ]);

        $middleware->alias([
            'account.owner' => EnsureAccountOwnership::class,
            'service.connected' => EnsureServiceConnected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
