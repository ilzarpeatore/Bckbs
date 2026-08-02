<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminApi
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is banned or inactive.'], 403);
        }

        return $next($request);
    }
}
