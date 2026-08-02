<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Subscription;
use App\Services\PackageFulfillmentService;

class CheckSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check subscription';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = now();

        User::where('is_subscribe', 1)->where(function ($query) use ($today) {
                $query->whereHas('subscriptionPackage', function ($q) use ($today) {
                    $q->where('subscription_end_date', '<', $today);
                })
                ->orWhereDoesntHave('subscriptionPackage');
            })->update(['is_subscribe' => 0]);

        // AÑADIDO — Fase 2 "Niveles de acceso": antes de marcarlas inactivas
        // (bulk update de abajo, que no dispara eventos de modelo), retira del
        // calendario del cliente solo las entradas futuras de cada Subscription
        // ligada a un Package de contenido (Training/MealPlanTemplate). No-op
        // para suscripciones que no tengan nada que limpiar.
        Subscription::where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
            ->where('subscription_end_date', '<', $today)
            ->whereNull('access_revoked_at')
            ->each(fn ($subscription) => PackageFulfillmentService::revokeAccess($subscription));

        Subscription::where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
            ->where('subscription_end_date', '<', $today)
            ->update(['status' => config('constant.SUBSCRIPTION_STATUS.INACTIVE')]);
        /*
        $user_list = User::where('is_subscribe', 1)->with('subscriptionPackage')->get();

        $today = now();
        foreach ($user_list as $key => $user) {
            
            $subscription = $user->subscriptionPackage;
            if( !$subscription ) {
                $user->update(['is_subscribe' => 0]);
                continue;
            }
            
            if ($subscription->subscription_end_date < $today) {
                $user->update(['is_subscribe' => 0]);

                $subscription->update([
                    'status' => config('constant.SUBSCRIPTION_STATUS.INACTIVE')
                ]);
            }
            // \Log::info('No subscriber');
        }
        */
    }
}
