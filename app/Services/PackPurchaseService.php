<?php

namespace App\Services;

use App\Mail\PackPurchaseMail;
use App\Models\PackPurchase;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Conecta una compra hecha en la web (Stripe, sin cuenta) con la cuenta de
 * la app (docs/PACKS_WEB.md):
 *
 * 1. recordPaidSession(): el webhook de Stripe (o la página de gracias, si
 *    el webhook tarda) registra la compra con un código de canje y manda el
 *    email de confirmación. Si ya existe una cuenta con ese email, se vincula
 *    en el acto.
 * 2. claimPendingByEmail(): al registrarse en la app con el mismo email, se
 *    vinculan sus compras pendientes.
 * 3. redeem(): si se registró con otro email, canjea el código.
 *
 * Vincular crea la PlanSubscription, pero el contenido (programa, nutrición,
 * hábitos, recursos) se asigna al TERMINAR el onboarding (startPendingFor),
 * para que el coach tenga sus datos antes del día 1 y el calendario empiece
 * cuando el cliente de verdad empieza. Si ya lo había terminado, en el acto.
 */
class PackPurchaseService
{
    /**
     * @param object $session Checkout Session de Stripe (objeto del evento o recuperada por API).
     */
    public static function recordPaidSession(object $session): ?PackPurchase
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return null;
        }

        $existing = PackPurchase::where('stripe_session_id', $session->id)->first();
        if ($existing) {
            return $existing;
        }

        $planId = $session->metadata->plan_id ?? null;
        $plan = $planId ? Plan::find($planId) : null;
        if (!$plan) {
            Log::error("Packs: sesión de Stripe pagada sin plan válido (session={$session->id}, plan_id={$planId}).");
            return null;
        }

        $email = PackPurchase::normalizeEmail($session->customer_details->email ?? $session->customer_email ?? null);
        if ($email === '') {
            Log::error("Packs: sesión de Stripe pagada sin email (session={$session->id}).");
            return null;
        }

        try {
            $purchase = PackPurchase::create([
                'plan_id' => $plan->id,
                'email' => $email,
                'customer_name' => $session->customer_details->name ?? null,
                'stripe_session_id' => $session->id,
                'stripe_payment_intent_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'amount_cents' => (int) ($session->amount_total ?? 0),
                'currency' => strtoupper($session->currency ?? $plan->currency ?? 'EUR'),
                'status' => PackPurchase::STATUS_PAID,
                'redeem_code' => PackPurchase::generateRedeemCode(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Webhook y página de gracias a la vez: la otra petición ganó la carrera.
            return PackPurchase::where('stripe_session_id', $session->id)->first();
        }

        self::sendConfirmation($purchase);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user) {
            self::claim($purchase, $user);
        }

        return $purchase->fresh();
    }

    public static function claimPendingByEmail(User $user): void
    {
        PackPurchase::where('email', PackPurchase::normalizeEmail($user->email))
            ->where('status', PackPurchase::STATUS_PAID)
            ->get()
            ->each(fn (PackPurchase $purchase) => self::claim($purchase, $user));
    }

    /**
     * @return array{ok: bool, message: string, purchase?: PackPurchase}
     */
    public static function redeem(User $user, string $code): array
    {
        $purchase = PackPurchase::where('redeem_code', PackPurchase::normalizeCode($code))->first();

        if (!$purchase || $purchase->status === PackPurchase::STATUS_REFUNDED) {
            return ['ok' => false, 'message' => 'Código no válido.'];
        }
        if ($purchase->status === PackPurchase::STATUS_CLAIMED) {
            return $purchase->user_id === $user->id
                ? ['ok' => true, 'message' => 'Este código ya está activado en tu cuenta.', 'purchase' => $purchase]
                : ['ok' => false, 'message' => 'Este código ya se ha usado en otra cuenta.'];
        }

        self::claim($purchase, $user);

        return ['ok' => true, 'message' => 'Código activado.', 'purchase' => $purchase->fresh()];
    }

    public static function claim(PackPurchase $purchase, User $user): void
    {
        DB::transaction(function () use ($purchase, $user) {
            $purchase = PackPurchase::lockForUpdate()->find($purchase->id);
            if (!$purchase || $purchase->status !== PackPurchase::STATUS_PAID) {
                return;
            }

            $plan = $purchase->plan;
            $subscription = PlanSubscription::create([
                'subscriber_type' => User::class,
                'subscriber_id' => $user->id,
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'slug' => 'pack-' . $purchase->id,
                'total_amount' => $purchase->amount_cents / 100,
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'amount_paid_cents' => $purchase->amount_cents,
                'payment_notes' => "Pack comprado en la web (compra #{$purchase->id}, sesión {$purchase->stripe_session_id}).",
                // Sin fechas hasta que empiece (startSubscription).
                'starts_at' => null,
                'ends_at' => null,
            ]);

            $purchase->update([
                'status' => PackPurchase::STATUS_CLAIMED,
                'user_id' => $user->id,
                'plan_subscription_id' => $subscription->id,
                'claimed_at' => now(),
            ]);
        });

        if ($user->onboarding_completed_at !== null) {
            self::startPendingFor($user);
        }
    }

    /** Arranca los packs vinculados que esperaban a que el cliente terminara el onboarding. */
    public static function startPendingFor(User $user): void
    {
        PackPurchase::where('user_id', $user->id)
            ->where('status', PackPurchase::STATUS_CLAIMED)
            ->whereNotNull('plan_subscription_id')
            ->get()
            ->each(function (PackPurchase $purchase) {
                $subscription = $purchase->subscription;
                if (!$subscription || $subscription->starts_at !== null || $subscription->fulfilled_at !== null) {
                    return;
                }
                $start = Carbon::now()->startOfDay();
                $subscription->update([
                    'starts_at' => $start,
                    'ends_at' => PlanFulfillmentService::computeEndDate($subscription->plan, $start),
                ]);
                PlanFulfillmentService::fulfill($subscription);
            });
    }

    /** Devolución hecha en Stripe: se marca y se retira el contenido futuro. */
    public static function markRefunded(string $paymentIntentId): void
    {
        PackPurchase::where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', '!=', PackPurchase::STATUS_REFUNDED)
            ->get()
            ->each(function (PackPurchase $purchase) {
                if ($purchase->subscription) {
                    PlanFulfillmentService::revokeAccess($purchase->subscription);
                    $purchase->subscription->update(['canceled_at' => now()]);
                }
                $purchase->update(['status' => PackPurchase::STATUS_REFUNDED, 'refunded_at' => now()]);
            });
    }

    public static function sendConfirmation(PackPurchase $purchase): void
    {
        $purchaseId = $purchase->id;
        app()->terminating(function () use ($purchase, $purchaseId) {
            try {
                Mail::to($purchase->email)->send(new PackPurchaseMail($purchase));
            } catch (\Throwable $e) {
                Log::warning("Packs: no se pudo enviar el email de la compra {$purchaseId}", ['error' => $e->getMessage()]);
            }
        });
    }
}
