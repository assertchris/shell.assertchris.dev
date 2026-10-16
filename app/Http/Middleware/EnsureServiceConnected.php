<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServiceConnected
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $service): Response
    {
        $account = $request->route('account');

        if (! $account instanceof Account) {
            return $next($request);
        }

        $connected = match ($service) {
            'github' => $account->isGitHubConnected(),
            'jira' => $account->isJiraConnected(),
            'laravel-cloud' => $account->isLaravelCloudConnected(),
            'slack' => $account->isSlackConnected(),
            'mail' => (bool) $account->mail_used,
            'calendar' => $account->isGoogleCalendarConnected(),
            default => throw new \InvalidArgumentException("Unknown service: {$service}"),
        };

        abort_unless($connected, 404);

        return $next($request);
    }
}
