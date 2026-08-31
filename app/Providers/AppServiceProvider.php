<?php

namespace App\Providers;

use App\Models\AdminLoginDevice;
use App\Models\ClientExerciseLog;
use App\Models\PainReport;
use App\Observers\ClientExerciseLogObserver;
use App\Observers\PainReportObserver;
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
        //
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
