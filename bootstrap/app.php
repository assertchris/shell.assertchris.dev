<?php

use App\Exceptions\Jira\JiraClientException;
use App\Exceptions\LaravelCloud\LaravelCloudAuthException;
use App\Exceptions\LaravelCloud\LaravelCloudClientException;
use App\Exceptions\LaravelCloud\LaravelCloudRateLimitException;
use App\Http\Middleware\EnforceTokenIpBinding;
use App\Http\Middleware\EnsureAccountOwnership;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureServiceConnected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;

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
            'permission' => EnsurePermission::class,
            'service.connected' => EnsureServiceConnected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (JiraClientException $e): JsonResponse {
            $status = $e->statusCode >= 400 && $e->statusCode < 600 ? $e->statusCode : 502;

            return response()->json(['error' => $e->getMessage()], $status);
        });

        $exceptions->renderable(function (LaravelCloudAuthException $e): JsonResponse {
            return response()->json(['error' => $e->getMessage()], 401);
        });

        $exceptions->renderable(function (LaravelCloudRateLimitException $e): JsonResponse {
            return response()->json(['error' => $e->getMessage()], 429, ['Retry-After' => $e->retryAfter]);
        });

        $exceptions->renderable(function (LaravelCloudClientException $e): JsonResponse {
            return response()->json(['error' => $e->getMessage()], 502);
        });
    })->create();
