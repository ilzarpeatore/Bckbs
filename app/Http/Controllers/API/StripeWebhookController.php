<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PackPurchaseService;
use App\Services\Stripe\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

/**
 * Webhook de Stripe para los packs vendidos en la web (docs/PACKS_WEB.md).
 * Dashboard de Stripe -> Developers -> Webhooks -> endpoint
 * https://<backend>/api/webhooks/stripe con los eventos
 * checkout.session.completed, checkout.session.async_payment_succeeded y
 * charge.refunded. Su "Signing secret" va en STRIPE_WEBHOOK_SECRET.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeGateway $stripe)
    {
        if (!config('services.stripe.webhook_secret')) {
            Log::error('Stripe webhook recibido sin STRIPE_WEBHOOK_SECRET configurado.');
            return response()->json(['error' => 'not_configured'], 500);
        }

        try {
            $event = $stripe->constructEvent($request->getContent(), $request->header('Stripe-Signature'));
        } catch (\UnexpectedValueException | SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma o payload no válidos.');
            return response()->json(['error' => 'invalid'], 400);
        }

        $object = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                // Solo registra si payment_status === 'paid' (los pagos
                // diferidos llegan después con async_payment_succeeded).
                PackPurchaseService::recordPaidSession($object);
                break;
            case 'charge.refunded':
                if (!empty($object->payment_intent)) {
                    PackPurchaseService::markRefunded((string) $object->payment_intent);
                }
                break;
        }

        // 200 también para eventos que no interesan: si no, Stripe reintenta.
        return response()->json(['received' => true]);
    }
}
