<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnforceTokenIpBinding
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $currentIp = $request->ip();

        if ($token->last_ip_address === null) {
            $token->update([
                'last_ip_address' => $currentIp,
            ]);

            return $next($request);
        }

        if ($token->last_ip_address === $currentIp) {
            return $next($request);
        }

        if ($token->ip_changed_at?->isToday()) {
            Log::warning('Token used from multiple IPs in one day', [
                'token_id' => $token->id,
                'token_name' => $token->name,
                'allowed_ip' => $token->last_ip_address,
                'blocked_ip' => $currentIp,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token->update([
            'last_ip_address' => $currentIp,
            'ip_changed_at' => now(),
        ]);

        return $next($request);
    }
}
