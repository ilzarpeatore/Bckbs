<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Services\PlanFulfillmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Sustituye a la "Fase 4" (portar payment_screen.tsx) — el pago ya no se iba
 * a procesar dentro de la app (Apple/Google exigen su propio IAP para
 * contenido digital comprado dentro de la app). Ahora el checkout real vive
 * en una página web fuera de este repo (bestronger.es), y este endpoint solo
 * ESCUCHA la confirmación de pago para conceder el acceso — mismo resultado
 * final que PlanSubscriptionController::grantPlan() (admin manual), pero
 * disparado automáticamente. Contrato esperado de la Checkout Session
 * creada en la web: client_reference_id = users.id, metadata.plan_id = plans.id.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            Log::error('Stripe webhook recibido sin STRIPE_WEBHOOK_SECRET configurado.');
            return response()->json(['error' => 'not_configured'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: payload invalido.');
            return response()->json(['error' => 'invalid_payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma invalida.');
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event);
        }

        // Cualquier otro tipo de evento se responde 200 sin hacer nada -
        // evita que Stripe siga reintentando eventos que no nos interesan
        // (ej. actualizaciones de metodo de pago, facturas de otros productos).
        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(\Stripe\Event $event): void
    {
        $session = $event->data->object;

        // Idempotencia: si Stripe reintenta la entrega del mismo evento
        // (no respondimos 2xx a tiempo, timeout, etc.), esto ya existe y no
        // se crea ni se cumple una segunda vez.
        if (PlanSubscription::where('stripe_event_id', $event->id)->exists()) {
            return;
        }

        $userId = $session->client_reference_id;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            Log::error("Stripe webhook: checkout.session.completed sin client_reference_id o metadata.plan_id (session={$session->id}).");
            return;
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            Log::error("Stripe webhook: usuario o plan no encontrado (user_id={$userId}, plan_id={$planId}, session={$session->id}).");
            return;
        }

        $start = Carbon::now()->startOfDay();
        $end = PlanFulfillmentService::computeEndDate($plan, $start);

        $subscription = PlanSubscription::create([
            'subscriber_type'   => User::class,
            'subscriber_id'     => $user->id,
            'plan_id'           => $plan->id,
            'name'              => "Suscripción {$plan->name}",
            'slug'              => 'main',
            'total_amount'      => $plan->price + $plan->signup_fee,
            'payment_status'    => 'paid',
            'payment_method'    => 'stripe',
            'amount_paid_cents' => $session->amount_total,
            'stripe_event_id'   => $event->id,
            'trial_ends_at'     => $plan->hasTrial() ? $start->copy()->addDays($plan->trial_period) : null,
            'starts_at'         => $start,
            'ends_at'           => $end,
        ]);

        PlanFulfillmentService::fulfill($subscription);

        Log::info("Stripe webhook: PlanSubscription {$subscription->id} creada y cumplida para user_id={$user->id}, plan_id={$plan->id}.");
    }
}
