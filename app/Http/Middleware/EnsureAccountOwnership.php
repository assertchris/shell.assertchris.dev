<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->route('account');

        if (! $account instanceof Account) {
            return $next($request);
        }

        abort_unless($account->user_id === $request->user()->id, 404);

        return $next($request);
    }
}
