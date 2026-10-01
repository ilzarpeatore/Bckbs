<?php

namespace App\Services\Stripe;

use App\Models\Plan;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Única puerta a la API de Stripe para los packs vendidos en la web
 * (docs/PACKS_WEB.md). Aislada en una clase para poder sustituirla en los
 * tests (el contenedor la resuelve; ver tests/Feature/PackPurchaseTest.php).
 *
 * Claves en el .env del servidor: STRIPE_SECRET_KEY y STRIPE_WEBHOOK_SECRET
 * (nunca en el repo).
 */
class StripeGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * Checkout de pago único para un pack. El precio sale del propio Plan
     * (price_data), así que no hace falta crear productos a mano en Stripe.
     * Los métodos de pago (tarjeta, Bizum, Apple/Google Pay...) los decide la
     * configuración de la cuenta en el Dashboard de Stripe.
     *
     * @return object con ->id y ->url
     */
    public function createCheckoutSession(Plan $plan, string $successUrl, string $cancelUrl, ?string $email = null): object
    {
        $product = array_filter([
            'name' => $plan->name,
            'description' => $plan->short_description ?: null,
            'images' => $plan->image_url ? [$plan->image_url] : null,
        ]);

        $params = [
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency ?: 'EUR'),
                    'unit_amount' => (int) round(((float) $plan->price + (float) $plan->signup_fee) * 100),
                    'product_data' => $product,
                ],
            ]],
            'metadata' => ['plan_id' => (string) $plan->id],
            'payment_intent_data' => ['metadata' => ['plan_id' => (string) $plan->id]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'allow_promotion_codes' => true,
            'locale' => 'es',
            // Cestas abandonadas (docs/MARKETING_WEB.md): la sesión caduca a las
            // pocas horas; si el comprador aceptó comunicaciones (casilla que
            // Stripe muestra donde la ley la exige), al caducar Stripe genera un
            // enlace para retomar el pago y le enviamos un único recordatorio.
            'expires_at' => now()->addHours(min(24, max(1, (int) config('services.stripe.checkout_expires_hours', 3))))->timestamp,
            'consent_collection' => ['promotions' => 'auto'],
            'after_expiration' => ['recovery' => ['enabled' => true, 'allow_promotion_codes' => true]],
        ];
        if ($email) {
            $params['customer_email'] = $email;
        }

        return $this->client()->checkout->sessions->create($params);
    }

    public function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, []);
    }

    /** Verifica la firma del webhook (lanza excepción si no es válida). */
    public function constructEvent(string $payload, ?string $signature): Event
    {
        return Webhook::constructEvent($payload, (string) $signature, (string) config('services.stripe.webhook_secret'));
    }
}
