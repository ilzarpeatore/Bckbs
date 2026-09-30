<?php

namespace Tests\Feature;

use App\Mail\PackPurchaseMail;
use App\Models\Habit;
use App\Models\PackPurchase;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\PackPurchaseService;
use App\Services\Stripe\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Packs vendidos en la web (docs/PACKS_WEB.md): pago en Stripe sin cuenta ->
 * webhook -> vinculación por email o código -> contenido al terminar el
 * onboarding.
 */
class PackPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
            'services.packs.web_url' => 'https://bestronger.test',
        ]);
        Mail::fake();
    }

    private function makeUser(string $email, bool $onboarded = true): User
    {
        $user = User::create([
            'first_name' => 'Ana',
            'last_name' => 'López',
            'username' => 'user_' . uniqid(),
            'email' => $email,
            'password' => bcrypt('password'),
            'user_type' => 'user',
            'status' => 'active',
            'login_type' => 'manual',
        ]);
        $user->assignRole('user');
        if ($onboarded) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return $user;
    }

    private function makePack(array $overrides = []): Plan
    {
        $habit = Habit::create(['title' => 'Beber 2 L de agua', 'frequency' => 'daily']);
        $coach = $this->makeUser('coach_' . uniqid() . '@example.test');
        $resource = Resource::create([
            'coach_id' => $coach->id,
            'title' => 'Guía de técnica de hip thrust',
            'type' => 'article',
            'scope' => 'assigned',
        ]);

        return Plan::create(array_merge([
            'name' => 'Glúteo 3 meses',
            'slug' => 'gluteo-3-meses',
            'price' => 49,
            'currency' => 'EUR',
            'invoice_period' => 3,
            'invoice_interval' => 'month',
            'is_active' => true,
            'sold_on_web' => true,
            'habit_template_ids' => [$habit->id],
            'resource_ids' => [$resource->id],
        ], $overrides));
    }

    private function sessionPayload(Plan $plan, string $email, string $sessionId = 'cs_test_1', string $paymentStatus = 'paid'): array
    {
        return [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'amount_total' => 4900,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_1',
            'customer_details' => ['email' => $email, 'name' => 'Ana López'],
            'metadata' => ['plan_id' => (string) $plan->id],
        ];
    }

    private function postWebhook(string $type, array $object, ?string $secret = null)
    {
        $payload = json_encode([
            'id' => 'evt_' . uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    // ═══ Catálogo y checkout (públicos) ═══════════════════════════════

    public function test_catalog_only_lists_active_packs_sold_on_web(): void
    {
        $this->makePack();
        Plan::create(['name' => 'Coaching presencial', 'slug' => 'presencial', 'price' => 99, 'is_active' => true, 'sold_on_web' => false]);

        $response = $this->getJson('/api/pack-catalog')->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('gluteo-3-meses', $response->json('data.0.slug'));
        $this->assertEqualsCanonicalizing(['habits', 'resources'], $response->json('data.0.includes'));
    }

    public function test_checkout_creates_stripe_session_for_pack(): void
    {
        $plan = $this->makePack();
        $this->app->instance(StripeGateway::class, new class extends StripeGateway {
            public function createCheckoutSession(Plan $plan, string $successUrl, string $cancelUrl, ?string $email = null): object
            {
                return (object) ['id' => 'cs_test_x', 'url' => "https://checkout.stripe.test/{$plan->slug}?ok=" . urlencode($successUrl)];
            }
        });

        $response = $this->postJson('/api/pack-checkout', ['slug' => $plan->slug])->assertStatus(200);

        $this->assertStringContainsString('gluteo-3-meses', $response->json('data.url'));
        $this->assertStringContainsString(urlencode('https://bestronger.test/packs/gracias?session_id={CHECKOUT_SESSION_ID}'), $response->json('data.url'));
    }

    public function test_checkout_rejects_pack_not_sold_on_web(): void
    {
        Plan::create(['name' => 'Coaching presencial', 'slug' => 'presencial', 'price' => 99, 'is_active' => true, 'sold_on_web' => false]);

        $this->postJson('/api/pack-checkout', ['slug' => 'presencial'])->assertStatus(404);
    }

    // ═══ Webhook ══════════════════════════════════════════════════════

    public function test_webhook_rejects_invalid_signature(): void
    {
        $plan = $this->makePack();

        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'ana@example.test'), 'whsec_wrong')
            ->assertStatus(400);

        $this->assertSame(0, PackPurchase::count());
    }

    public function test_webhook_records_purchase_and_sends_code_when_no_account_yet(): void
    {
        $plan = $this->makePack();

        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'Ana@Example.test'))->assertStatus(200);
        // Stripe reintenta el mismo evento: no se duplica.
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'Ana@Example.test'))->assertStatus(200);

        $this->assertSame(1, PackPurchase::count());
        $purchase = PackPurchase::first();
        $this->assertSame('ana@example.test', $purchase->email);
        $this->assertSame(PackPurchase::STATUS_PAID, $purchase->status);
        $this->assertSame(10, strlen($purchase->redeem_code));
        Mail::assertQueued(PackPurchaseMail::class, fn ($mail) => $mail->hasTo('ana@example.test'));
    }

    public function test_webhook_ignores_unpaid_sessions(): void
    {
        $plan = $this->makePack();

        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'ana@example.test', 'cs_x', 'unpaid'))
            ->assertStatus(200);

        $this->assertSame(0, PackPurchase::count());
    }

    public function test_purchase_by_existing_onboarded_user_is_assigned_immediately(): void
    {
        $user = $this->makeUser('ana@example.test');
        $plan = $this->makePack();

        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'ana@example.test'))->assertStatus(200);

        $purchase = PackPurchase::first();
        $this->assertSame(PackPurchase::STATUS_CLAIMED, $purchase->status);
        $this->assertSame($user->id, $purchase->user_id);
        $subscription = $purchase->subscription;
        $this->assertNotNull($subscription->fulfilled_at);
        $this->assertSame(now()->addMonths(3)->toDateString(), $subscription->ends_at->toDateString());
        $this->assertTrue(Habit::forClient($user->id)->where('source_template_id', $plan->habit_template_ids[0])->exists());
        $this->assertTrue(Resource::find($plan->resource_ids[0])->assignedClients()->where('users.id', $user->id)->exists());
    }

    // ═══ Registro con el mismo email y fin del onboarding ═════════════

    public function test_register_with_same_email_links_pack_and_starts_it_after_onboarding(): void
    {
        $plan = $this->makePack();
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'nueva@example.test'))->assertStatus(200);

        $user = $this->makeUser('Nueva@example.test', onboarded: false);
        PackPurchaseService::claimPendingByEmail($user);

        $purchase = PackPurchase::first();
        $this->assertSame(PackPurchase::STATUS_CLAIMED, $purchase->status);
        $this->assertNull($purchase->subscription->fulfilled_at, 'No debe empezar antes de terminar el onboarding');
        $this->assertFalse(Habit::forClient($user->id)->exists());

        $user->forceFill(['onboarding_completed_at' => now()])->save();
        PackPurchaseService::startPendingFor($user);

        $this->assertNotNull($purchase->subscription->fresh()->fulfilled_at);
        $this->assertTrue(Habit::forClient($user->id)->exists());
    }

    public function test_register_endpoint_claims_pending_purchase(): void
    {
        $plan = $this->makePack();
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'registro@example.test'))->assertStatus(200);

        $this->postJson('/api/register', [
            'first_name' => 'Laura',
            'last_name' => 'Gil',
            'username' => 'laura_' . uniqid(),
            'email' => 'registro@example.test',
            'password' => 'password123',
            'user_type' => 'user',
        ])->assertSuccessful();

        $this->assertSame(PackPurchase::STATUS_CLAIMED, PackPurchase::first()->status);
    }

    // ═══ Código de canje ═════════════════════════════════════════════

    public function test_redeem_code_links_purchase_made_with_another_email(): void
    {
        $plan = $this->makePack();
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'trabajo@example.test'))->assertStatus(200);
        $code = PackPurchase::first()->redeem_code;

        $user = $this->makeUser('personal@example.test');
        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson('/api/v1/pack-redeem', ['code' => strtolower(substr($code, 0, 5) . '-' . substr($code, 5))])
            ->assertStatus(200);

        $this->assertTrue($response->json('data.started'));
        $this->assertSame($user->id, PackPurchase::first()->user_id);

        // Otra cuenta no puede reutilizarlo.
        Sanctum::actingAs($this->makeUser('otra@example.test'), ['*']);
        $this->postJson('/api/v1/pack-redeem', ['code' => $code])->assertStatus(422);
    }

    public function test_redeem_rejects_unknown_code(): void
    {
        Sanctum::actingAs($this->makeUser('ana@example.test'), ['*']);

        $this->postJson('/api/v1/pack-redeem', ['code' => 'NOEXISTE22'])->assertStatus(422);
    }

    // ═══ Página de gracias y devoluciones ════════════════════════════

    public function test_checkout_status_shows_email_and_code(): void
    {
        $plan = $this->makePack();
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'ana@example.test', 'cs_status'))->assertStatus(200);

        $response = $this->getJson('/api/pack-checkout-status?session_id=cs_status')->assertStatus(200);

        $this->assertSame('ana@example.test', $response->json('data.email'));
        $this->assertSame('Glúteo 3 meses', $response->json('data.pack'));
        $this->assertSame(PackPurchase::first()->redeem_code, $response->json('data.redeem_code'));
    }

    public function test_checkout_status_records_purchase_if_webhook_is_late(): void
    {
        $plan = $this->makePack();
        $payload = $this->sessionPayload($plan, 'ana@example.test', 'cs_late');
        $this->app->instance(StripeGateway::class, new class($payload) extends StripeGateway {
            public function __construct(private array $session) {}
            public function retrieveCheckoutSession(string $sessionId): object
            {
                return json_decode(json_encode($this->session));
            }
        });

        $this->getJson('/api/pack-checkout-status?session_id=cs_late')->assertStatus(200);

        $this->assertSame(1, PackPurchase::where('stripe_session_id', 'cs_late')->count());
    }

    public function test_refund_revokes_pack(): void
    {
        $this->makeUser('ana@example.test');
        $plan = $this->makePack();
        $this->postWebhook('checkout.session.completed', $this->sessionPayload($plan, 'ana@example.test'))->assertStatus(200);

        $this->postWebhook('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_1'])
            ->assertStatus(200);

        $purchase = PackPurchase::first();
        $this->assertSame(PackPurchase::STATUS_REFUNDED, $purchase->status);
        $this->assertNotNull($purchase->subscription->access_revoked_at);
    }
}
