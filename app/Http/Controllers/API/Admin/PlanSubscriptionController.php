<?php

namespace App\Http\Controllers\API\Admin;

use App\Mail\SubscriptionReminderMail;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\PlanSubscriptionUsage;
use App\Models\User;
use App\Http\Resources\PlanSubscriptionResource;
use App\Notifications\CommonNotification;
use App\Services\AuditLogger;
use App\Services\PlanFulfillmentService;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class PlanSubscriptionController extends BaseController
{
    protected function getModelClass(): string
    {
        return PlanSubscription::class;
    }

    protected function getResourceClass(): string
    {
        return PlanSubscriptionResource::class;
    }

    public function index(Request $request)
    {
        $query = PlanSubscription::with(['plan', 'subscriber']);

        if ($request->filled('search')) {
            FuzzySearch::apply($query, ['name', 'slug'], $request->search);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where(function ($q) {
                    $q->where('ends_at', '>', now())->orWhereNull('ends_at');
                });
            } elseif ($request->status === 'ended') {
                $query->where('ends_at', '<=', now());
            } elseif ($request->status === 'trial') {
                $query->where('trial_ends_at', '>', now());
            }
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = PlanSubscriptionResource::collection($items);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ]);
    }

    /**
     * Admin tool: grant a plan to a client without real payment.
     */
    public function grantPlan(Request $request)
    {
        $validated = $request->validate([
            'subscriber_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $start = Carbon::now()->startOfDay();
        $end = PlanFulfillmentService::computeEndDate($plan, $start);

        $subscription = PlanSubscription::create([
            'subscriber_type' => 'App\\Models\\User',
            'subscriber_id' => $validated['subscriber_id'],
            'plan_id' => $plan->id,
            'name' => "Suscripción {$plan->name}",
            'slug' => 'main',
            'total_amount' => $plan->price + $plan->signup_fee,
            'payment_status' => 'paid',
            'trial_ends_at' => $plan->hasTrial()
                ? $start->copy()->addDays($plan->trial_period)
                : null,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        PlanFulfillmentService::fulfill($subscription);

        AuditLogger::log(
            'grant_plan',
            'plan_subscriptions',
            $subscription->id,
            "Plan \"{$plan->name}\" concedido al usuario {$validated['subscriber_id']}."
        );

        return json_custom_response([
            'message' => 'Plan concedido.',
            'data' => new PlanSubscriptionResource($subscription->fresh(['plan', 'subscriber'])),
        ], 201);
    }

    /**
     * Uso de features por suscripción.
     */
    public function usage(Request $request)
    {
        $query = PlanSubscriptionUsage::with(['feature', 'subscription']);

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        $data = $items->map(function (PlanSubscriptionUsage $u) {
            return [
                'id' => $u->id,
                'subscription_id' => $u->subscription_id,
                'feature_id' => $u->feature_id,
                'feature_name' => $u->feature?->name,
                'used' => $u->used,
                'valid_until' => $u->valid_until?->toISOString(),
                'created_at' => $u->created_at?->toISOString(),
            ];
        });

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $data,
        ]);
    }

    /**
     * Métricas del panel de suscripciones (MRR, ARPU, activas, vencidas...).
     */
    public function stats(Request $request)
    {
        $now = now();

        $active = PlanSubscription::with('plan')
            ->whereNull('canceled_at')
            ->whereNull('access_revoked_at')
            ->where(function ($q) use ($now) {
                $q->where('ends_at', '>', $now)->orWhereNull('ends_at');
            })
            ->get();

        $expired = PlanSubscription::whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->count();

        $canceledThisMonth = PlanSubscription::whereNotNull('canceled_at')
            ->whereYear('canceled_at', $now->year)
            ->whereMonth('canceled_at', $now->month)
            ->count();

        $mrr = 0;
        $activeSubscriberIds = [];

        foreach ($active as $sub) {
            if ($sub->plan) {
                $mrr += $this->monthlyEquivalent($sub->plan);
            }
            $activeSubscriberIds[$sub->subscriber_id] = true;
        }

        $activeSubscribers = count($activeSubscriberIds);

        $revenueThisMonth = PlanSubscription::where('payment_status', 'paid')
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->sum('amount_paid_cents') / 100;

        $pendingAmount = PlanSubscription::where('payment_status', 'pending')
            ->sum('total_amount');

        $expiringSoon = PlanSubscription::whereNull('canceled_at')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addDays(7))
            ->count();

        return json_custom_response([
            'data' => [
                'mrr' => round($mrr, 2),
                'arpu' => $activeSubscribers > 0 ? round($mrr / $activeSubscribers, 2) : 0,
                'active_subscriptions' => $active->count(),
                'expired_subscriptions' => $expired,
                'canceled_this_month' => $canceledThisMonth,
                'expiring_soon' => $expiringSoon,
                'revenue_this_month' => round($revenueThisMonth, 2),
                'pending_amount' => round((float) $pendingAmount, 2),
            ],
        ]);
    }

    /**
     * Envía recordatorio de renovación por email y/o push.
     */
    public function reminder(Request $request)
    {
        $request->validate([
            'subscription_ids' => 'required|array|min:1',
            'subscription_ids.*' => 'integer|exists:plan_subscriptions,id',
            'channel' => 'sometimes|in:push,email,both',
        ]);

        $channel = $request->get('channel', 'email');

        $subscriptions = PlanSubscription::with(['plan', 'subscriber'])
            ->whereIn('id', $request->subscription_ids)
            ->get();

        if ($subscriptions->isEmpty()) {
            return json_message_response('No se encontraron suscripciones.', 404);
        }

        $sent = 0;
        $ids = [];

        foreach ($subscriptions as $subscription) {
            $user = $subscription->subscriber;

            if (!$user) {
                continue;
            }

            $ids[] = $subscription->id;

            $daysLeft = $subscription->ends_at
                ? max(0, (int) now()->diffInDays($subscription->ends_at, false))
                : null;

            $planName = $subscription->plan?->name ?? 'tu suscripción';

            if (in_array($channel, ['push', 'both'], true) && $user->player_id) {
                try {
                    $user->notify(new CommonNotification('subscription_expiring', [
                        'id' => $subscription->id,
                        'type' => 'subscription_expiring',
                        'subject' => 'Tu suscripción está a punto de expirar',
                        'message' => "\"{$planName}\" expira en " . ($daysLeft ?? 0) . ' días. Renuévala para no perder el acceso.',
                    ]));
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if (in_array($channel, ['email', 'both'], true) && $user->email) {
                try {
                    Mail::to($user->email)->send(new SubscriptionReminderMail([
                        'subscriber_name' => $user->display_name ?? trim($user->first_name . ' ' . $user->last_name),
                        'plan_name' => $planName,
                        'ends_at' => $subscription->ends_at?->format('d/m/Y'),
                        'days_left' => $daysLeft,
                    ]));
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        AuditLogger::log(
            'send_reminder',
            'plan_subscriptions',
            null,
            "Recordatorio de renovación ({$channel}) enviado a " . count($ids) . " suscripción(es): " . implode(', ', $ids)
        );

        return json_custom_response([
            'message' => "Aviso de renovación enviado a " . count($ids) . " suscripción(es)",
            'data' => [
                'sent' => $sent,
                'ids' => $ids,
            ],
        ]);
    }

    /**
     * Revoca el acceso de un cliente (vence sus suscripciones activas y
     * limpia el contenido asociado vía PlanFulfillmentService).
     */
    public function revokeAccess(Request $request, $user)
    {
        $user = User::find($user);

        if (!$user) {
            return json_message_response('Usuario no encontrado.', 404);
        }

        $reason = $request->get('reason', 'Suscripción vencida');

        $subscriptions = PlanSubscription::where('subscriber_type', 'App\\Models\\User')
            ->where('subscriber_id', $user->id)
            ->whereNull('access_revoked_at')
            ->get();

        $revoked = 0;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->ends_at || $subscription->ends_at->gt(now())) {
                $subscription->canceled_at = $subscription->canceled_at ?? now();
                $subscription->ends_at = now();
                $subscription->saveQuietly();
            }

            PlanFulfillmentService::revokeAccess($subscription);
            $revoked++;
        }

        AuditLogger::log(
            'revoke_access',
            'users',
            $user->id,
            "Acceso revocado ({$reason}). Suscripciones afectadas: {$revoked}."
        );

        return json_custom_response([
            'message' => 'Acceso revocado y suscripción marcada como vencida.',
            'data' => [
                'user_id' => (int) $user->id,
                'reason' => $reason,
                'revoked_subscriptions' => $revoked,
            ],
        ]);
    }

    /**
     * Transacciones en el shape que consume el panel React
     * (user/plan anidados, amount en EUR, status de pago).
     */
    public function transactions(Request $request)
    {
        $query = PlanSubscription::with(['plan', 'subscriber'])
            ->whereNotNull('payment_method')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $subscriberIds = FuzzySearch::matchingIds(User::class, ['first_name', 'last_name', 'email'], $request->search);
            $planIds = FuzzySearch::matchingIds(Plan::class, ['name'], $request->search);
            $query->where(function ($q) use ($subscriberIds, $planIds) {
                $q->where(fn ($sq) => $sq->where('subscriber_type', User::class)->whereIn('subscriber_id', $subscriberIds))
                  ->orWhereIn('plan_id', $planIds);
            });
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        $items->getCollection()->transform(function (PlanSubscription $s) {
            $subscriber = $s->subscriber;

            return [
                'id' => $s->id,
                'user' => $subscriber
                    ? [
                        'id' => $subscriber->id,
                        'name' => $subscriber->display_name ?? trim($subscriber->first_name . ' ' . $subscriber->last_name),
                        'email' => $subscriber->email,
                    ]
                    : null,
                'plan' => $s->plan
                    ? [
                        'id' => $s->plan->id,
                        'name' => $s->plan->name,
                        'price' => (float) $s->plan->price,
                        'currency' => $s->plan->currency,
                    ]
                    : null,
                'amount' => $s->amount_paid_cents
                    ? round($s->amount_paid_cents / 100, 2)
                    : round((float) $s->total_amount, 2),
                'currency' => $s->plan?->currency ?: 'EUR',
                'payment_method' => $s->payment_method,
                'status' => $s->payment_status ?: 'pending',
                'paid_at' => $s->payment_status === 'paid'
                    ? ($s->starts_at?->toISOString() ?? $s->created_at?->toISOString())
                    : null,
            ];
        });

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items->items(),
        ]);
    }

    private function monthlyEquivalent(Plan $plan): float
    {
        $price = (float) $plan->price;
        $period = max((int) $plan->invoice_period, 1);

        if ($price <= 0) {
            return 0;
        }

        return match ($plan->invoice_interval) {
            'day' => $price * $period * 30,
            'week' => $price * $period * 4,
            'month' => $price * $period,
            'year' => ($price * $period) / 12,
            default => $price * $period,
        };
    }
}
