<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Role;
use App\Models\AdminLoginDevice;
use Nwidart\Modules\Facades\Module;

class UserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {   
        $user = auth()->user();

        if (Auth::check()) {
            if ($user->status == 'active') {
                $role = Role::whereIn('name', $user->getRoleNames())
                            ->where('status', 1)
                            ->first();

                if ($role == null) {
                    abort(403, __('message.contact_sytem_admin'));
                }

                $device = AdminLoginDevice::where('session_id', Session::getId())->first();
                if ($device && !$device->is_active) {
                    if (!$device->logout_at) {
                        $device->update(['logout_at' => now()]);
                    }

                    Auth::guard('web')->logout();
                    Session::invalidate();
                    Session::regenerateToken();

                    return redirect()->route('login');
                }
                return $next($request);
            } else {
                Auth::logout();
                abort(403, __('message.access_denied'));
            }          
        } else {
            if (Module::has('Frontend') && Module::isEnabled('Frontend')) {
                return redirect()->route('browse');
            } else {
                return $next($request);
            }
        }
    }
}
