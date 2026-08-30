<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Gate reutilizable para features nuevas que deban limitarse a clientes
        // de pago (subscriber o personal), sin ligarse a un Package/Programa/
        // Receta concreto -> para eso sigue usandose PackageAccessService.
        // Uso: Gate::allows('paid-tier') o $request->user()->can('paid-tier').
        Gate::define('paid-tier', function ($user) {
            return $user->access_tier !== 'free';
        });
    }
}
