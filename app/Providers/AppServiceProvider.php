<?php

namespace App\Providers;

use App\Models\AdminLoginDevice;
use App\Models\ClientExerciseLog;
use App\Models\PainReport;
use App\Observers\ClientExerciseLogObserver;
use App\Observers\PainReportObserver;
use App\Services\FatSecret\FatSecretClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // FatSecretClient necesita client_id/secret de config -- el
        // contenedor no puede resolverlos solo, se registran aquí para que
        // FatSecretFoodService/FatSecretRecipeService puedan pedirlo por
        // inyección normal. Ver docs/FATSECRET_INTEGRATION.md sección 1.
        $this->app->singleton(FatSecretClient::class, function () {
            return new FatSecretClient(
                config('services.fatsecret.client_id'),
                config('services.fatsecret.client_secret'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ClientExerciseLog::observe(ClientExerciseLogObserver::class);
        PainReport::observe(PainReportObserver::class);
    }
}
