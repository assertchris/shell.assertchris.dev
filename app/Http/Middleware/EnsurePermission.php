<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $account = $request->route('account');

        if (! $account instanceof Account) {
            return $next($request);
        }

        if (! array_key_exists($permission, $account->getCasts())) {
            throw new \InvalidArgumentException("Unknown permission: {$permission}");
        }

        if (! $account->{$permission}) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
