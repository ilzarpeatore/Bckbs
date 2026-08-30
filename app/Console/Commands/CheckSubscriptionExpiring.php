<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Notifications\CommonNotification;

/**
 * Aparte de CheckSubscription (que marca inactivas las YA vencidas), este
 * comando avisa con antelación (3 días) de una suscripción a punto de
 * expirar — no toca CheckSubscription porque ese hace updates masivos sin
 * cargar modelos individuales, no dispara notificaciones ahí.
 */
class CheckSubscriptionExpiring extends Command
{
    protected $signature = 'check:subscription-expiring';

    protected $description = 'Avisa a los clientes cuya suscripción expira en los próximos 3 días';

    public function handle()
    {
        $now = now();
        $soon = $now->copy()->addDays(3);

        Subscription::with(['user', 'package'])
            ->where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
            ->whereNull('expiring_notified_at')
            ->whereBetween('subscription_end_date', [$now, $soon])
            ->chunk(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    if (!$subscription->user) {
                        continue;
                    }

                    $daysLeft = $now->diffInDays($subscription->subscription_end_date, false);
                    $packageName = $subscription->package->name ?? 'tu suscripción';

                    $subscription->user->notify(new CommonNotification('subscription_expiring', [
                        'id'      => $subscription->id,
                        'type'    => 'subscription_expiring',
                        'subject' => 'Tu suscripción está a punto de expirar',
                        'message' => "\"{$packageName}\" expira en " . max(0, $daysLeft) . ' días. Renuévala para no perder el acceso.',
                    ]));

                    $subscription->update(['expiring_notified_at' => $now]);
                }
            });
    }
}
