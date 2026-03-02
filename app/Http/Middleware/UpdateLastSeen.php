<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->last_seen || $user->last_seen->lt(now()->subMinutes(2)))) {
            $user->update(['last_seen' => now()]);
        }

        return $next($request);
    }
}
