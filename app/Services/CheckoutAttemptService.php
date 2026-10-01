<?php

namespace App\Services;

use App\Mail\PackRecoveryMail;
use App\Models\PackCheckoutAttempt;
use App\Models\PackPurchase;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Intentos de compra de packs y cestas abandonadas (docs/MARKETING_WEB.md).
 *
 * started   → se creó la sesión de Stripe (alguien pulsó "Comprar").
 * completed → Stripe confirmó el pago de esa sesión.
 * expired   → la sesión caducó sin pago: cesta abandonada.
 * recovered → caducó, pero luego pagó con el enlace de recuperación.
 */
class CheckoutAttemptService
{
    /** No más de un recordatorio por email y pack en este plazo. */
    public const RECOVERY_COOLDOWN_DAYS = 7;

    public static function start(Plan $plan, object $session, ?string $email, array $attribution): ?PackCheckoutAttempt
    {
        try {
            return PackCheckoutAttempt::create(array_merge($attribution, [
                'plan_id' => $plan->id,
                'stripe_session_id' => $session->id,
                'email' => $email ?: null,
                'status' => PackCheckoutAttempt::STATUS_STARTED,
            ]));
        } catch (\Throwable $e) {
            // La analítica nunca debe impedir un pago.
            Log::warning('Packs: no se pudo registrar el intento de compra', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public static function markCompleted(object $session, PackPurchase $purchase): void
    {
        $attempt = PackCheckoutAttempt::where('stripe_session_id', $session->id)->first();
        if ($attempt) {
            $attempt->update([
                'status' => PackCheckoutAttempt::STATUS_COMPLETED,
                'email' => $attempt->email ?: $purchase->email,
                'amount_cents' => $purchase->amount_cents,
                'completed_at' => now(),
            ]);

            return;
        }

        // Pago con el enlace de recuperación: Stripe crea una sesión nueva. Se
        // atribuye al intento abandonado del mismo email y pack.
        $abandoned = PackCheckoutAttempt::where('plan_id', $purchase->plan_id)
            ->where('email', $purchase->email)
            ->where('status', PackCheckoutAttempt::STATUS_EXPIRED)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('id')
            ->first();
        $abandoned?->update([
            'status' => PackCheckoutAttempt::STATUS_RECOVERED,
            'amount_cents' => $purchase->amount_cents,
            'completed_at' => now(),
        ]);
    }

    public static function markExpired(object $session): ?PackCheckoutAttempt
    {
        $attempt = PackCheckoutAttempt::where('stripe_session_id', $session->id)->first();
        if (!$attempt || $attempt->status !== PackCheckoutAttempt::STATUS_STARTED) {
            return $attempt;
        }

        $email = PackPurchase::normalizeEmail($session->customer_details->email ?? $session->customer_email ?? $attempt->email);
        $attempt->update([
            'status' => PackCheckoutAttempt::STATUS_EXPIRED,
            'expired_at' => now(),
            'email' => $email !== '' ? $email : null,
            'recovery_consent' => ($session->consent->promotions ?? null) === 'opt_in',
            'recovery_url' => $session->after_expiration->recovery->url ?? null,
        ]);

        self::maybeSendRecovery($attempt->fresh());

        return $attempt;
    }

    /**
     * Un único email de recuperación, solo si: hay email, aceptó
     * comunicaciones en Stripe, Stripe dio enlace, el pack sigue a la venta,
     * no lo ha comprado ya y no se le ha escrito por este pack hace poco.
     */
    public static function maybeSendRecovery(PackCheckoutAttempt $attempt): bool
    {
        $plan = $attempt->plan;
        if (!$attempt->email || !$attempt->recovery_consent || !$attempt->recovery_url || $attempt->recovery_email_sent_at) {
            return false;
        }
        if (!$plan || $plan->trashed() || !$plan->is_active || !$plan->sold_on_web) {
            return false;
        }

        $alreadyBought = PackPurchase::where('plan_id', $plan->id)->where('email', $attempt->email)
            ->where('status', '!=', PackPurchase::STATUS_REFUNDED)->exists();
        $recentlyReminded = PackCheckoutAttempt::where('plan_id', $plan->id)->where('email', $attempt->email)
            ->where('recovery_email_sent_at', '>=', now()->subDays(self::RECOVERY_COOLDOWN_DAYS))->exists();
        if ($alreadyBought || $recentlyReminded) {
            return false;
        }

        try {
            Mail::to($attempt->email)->send(new PackRecoveryMail($attempt));
            $attempt->update(['recovery_email_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning("Packs: no se pudo enviar el email de recuperación del intento {$attempt->id}", ['error' => $e->getMessage()]);

            return false;
        }
    }
}
