<?php

namespace App\Http\Middleware\API;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->status != 'active') {
            
            $message = $user->statusMessage();
            
            // $user->currentAccessToken()->delete();
            return json_custom_response([
                'error' => 'user_'.$user->status,
                'message' => $message,
            ], 400);
            return json_message_response($message,400);
        }

        return $next($request);
    }
}