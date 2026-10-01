<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Mail\NewsletterConfirmMail;
use App\Mail\PackRecoveryMail;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\PackCheckoutAttempt;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WebPageview;
use App\Services\Stripe\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Marketing de la web (docs/MARKETING_WEB.md): newsletter con doble opt-in,
 * contacto, analítica sin cookies con atribución, y cestas abandonadas.
 */
class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';
    private const WEB_KEY = 'web-key-test';

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
            'services.packs.web_url' => 'https://bestronger.test',
            'services.web.server_key' => self::WEB_KEY,
            'services.web.contact_notify_email' => 'coach@bestronger.test',
        ]);
        Mail::fake();
    }

    private function web(): array
    {
        return ['X-Web-Key' => self::WEB_KEY, 'X-Client-IP' => '203.0.113.' . random_int(1, 250)];
    }

    private function makePack(): Plan
    {
        return Plan::create([
            'name' => 'Glúteo 12 semanas', 'slug' => 'gluteo-12', 'price' => 49, 'currency' => 'EUR',
            'invoice_period' => 12, 'invoice_interval' => 'week', 'is_active' => true, 'is_pack' => true, 'sold_on_web' => true,
        ]);
    }

    private function fakeStripe(string $sessionId = 'cs_attempt_1'): void
    {
        $this->app->instance(StripeGateway::class, new class($sessionId) extends StripeGateway {
            public function __construct(private string $id) {}
            public function createCheckoutSession(Plan $plan, string $successUrl, string $cancelUrl, ?string $email = null): object
            {
                return (object) ['id' => $this->id, 'url' => 'https://checkout.stripe.test/' . $this->id];
            }
        });
    }

    private function postWebhook(string $type, array $object)
    {
        $payload = json_encode(['id' => 'evt_' . uniqid(), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
        $ts = time();
        $sig = hash_hmac('sha256', "{$ts}.{$payload}", self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1={$sig}",
        ], $payload);
    }

    private function actingAsAdmin(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'username' => 'admin_' . uniqid(), 'email' => 'admin_' . uniqid() . '@example.test',
            'password' => bcrypt('x'), 'user_type' => 'admin', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);
    }

    // ═══ Newsletter ═══════════════════════════════════════════════════

    public function test_newsletter_double_opt_in_with_attribution(): void
    {
        $this->withHeaders($this->web())->postJson('/api/newsletter-subscribe', [
            'email' => 'Ana@Example.test',
            'source' => 'footer',
            'attribution' => ['utm_source' => 'instagram', 'utm_campaign' => 'gluteo-otono', 'landing_path' => '/packs/gluteo-12?utm_source=x'],
        ])->assertStatus(200)->assertJsonPath('data.status', 'pending');

        $sub = NewsletterSubscriber::firstOrFail();
        $this->assertSame('ana@example.test', $sub->email);
        $this->assertSame('pending', $sub->status);
        $this->assertSame('gluteo-otono', $sub->utm_campaign);
        $this->assertSame('/packs/gluteo-12', $sub->landing_path);
        Mail::assertQueued(NewsletterConfirmMail::class, fn ($m) => $m->hasTo('ana@example.test'));

        $this->postJson('/api/newsletter-confirm', ['token' => $sub->confirm_token])->assertStatus(200)->assertJsonPath('data.status', 'confirmed');
        $this->assertSame('confirmed', $sub->fresh()->status);

        // Volver a apuntarse no duplica ni reenvía.
        $this->postJson('/api/newsletter-subscribe', ['email' => 'ana@example.test'])->assertJsonPath('data.status', 'confirmed');
        $this->assertSame(1, NewsletterSubscriber::count());
        Mail::assertQueued(NewsletterConfirmMail::class, 1);

        $this->postJson('/api/newsletter-unsubscribe', ['token' => $sub->unsubscribe_token])->assertStatus(200);
        $this->assertSame('unsubscribed', $sub->fresh()->status);
        $this->postJson('/api/newsletter-confirm', ['token' => 'no-existe'])->assertStatus(404);
    }

    public function test_newsletter_honeypot_saves_nothing(): void
    {
        $this->postJson('/api/newsletter-subscribe', ['email' => 'bot@example.test', 'website' => 'http://spam'])->assertStatus(200);
        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_conversion_inherits_campaign_from_first_visit_of_the_day(): void
    {
        $this->withHeaders($this->web())->postJson('/api/track', [
            'path' => '/packs/gluteo-12',
            'attribution' => ['visitor_hash' => 'abcdef0123456789', 'utm_source' => 'meta', 'utm_campaign' => 'reels', 'click_id_type' => 'fbclid'],
        ])->assertStatus(200);

        $this->postJson('/api/newsletter-subscribe', ['email' => 'luis@example.test', 'attribution' => ['visitor_hash' => 'abcdef0123456789']])->assertStatus(200);

        $sub = NewsletterSubscriber::firstOrFail();
        $this->assertSame('meta', $sub->utm_source);
        $this->assertSame('reels', $sub->utm_campaign);
        $this->assertSame('fbclid', $sub->click_id_type);
    }

    // ═══ Contacto ═════════════════════════════════════════════════════

    public function test_contact_message_is_saved_and_notified(): void
    {
        $this->postJson('/api/contact-message', [
            'name' => 'Marta', 'email' => 'marta@example.test', 'subject' => 'Duda', 'message' => '¿El pack sirve para principiantes?',
        ])->assertStatus(200);

        $msg = ContactMessage::firstOrFail();
        $this->assertSame('new', $msg->status);
        Mail::assertQueued(ContactMessageMail::class, fn ($m) => $m->hasTo('coach@bestronger.test'));

        $this->postJson('/api/contact-message', ['name' => 'X', 'email' => 'no-es-email', 'message' => 'hola hola'])->assertStatus(422);
    }

    // ═══ Analítica ════════════════════════════════════════════════════

    public function test_track_only_accepts_the_web_server(): void
    {
        $this->postJson('/api/track', ['path' => '/home'])->assertStatus(403);
        $this->withHeaders(['X-Web-Key' => 'otra'])->postJson('/api/track', ['path' => '/home'])->assertStatus(403);

        $this->withHeaders($this->web())->postJson('/api/track', ['path' => '/home?x=1', 'device' => 'mobile', 'browser' => 'Safari'])->assertStatus(200);
        $view = WebPageview::firstOrFail();
        $this->assertSame('/home', $view->path);
        $this->assertSame('mobile', $view->device);
    }

    // ═══ Cestas abandonadas ═══════════════════════════════════════════

    public function test_checkout_attempt_records_campaign_and_completes_on_payment(): void
    {
        $plan = $this->makePack();
        $this->fakeStripe('cs_ok');

        $this->withHeaders($this->web())->postJson('/api/pack-checkout', [
            'slug' => $plan->slug,
            'email' => 'Eva@Example.test',
            'attribution' => ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'gluteo', 'click_id_type' => 'gclid'],
        ])->assertStatus(200);

        $attempt = PackCheckoutAttempt::firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertSame('eva@example.test', $attempt->email);
        $this->assertSame('gclid', $attempt->click_id_type);

        $this->postWebhook('checkout.session.completed', [
            'id' => 'cs_ok', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 4900, 'currency' => 'eur',
            'payment_intent' => 'pi_1', 'customer_details' => ['email' => 'eva@example.test'], 'metadata' => ['plan_id' => (string) $plan->id],
        ])->assertStatus(200);

        $attempt->refresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(4900, $attempt->amount_cents);
    }

    public function test_expired_session_is_abandoned_and_recovery_email_only_with_consent(): void
    {
        $plan = $this->makePack();
        $this->fakeStripe('cs_a');
        $this->postJson('/api/pack-checkout', ['slug' => $plan->slug])->assertStatus(200);
        $this->fakeStripe('cs_b');
        $this->postJson('/api/pack-checkout', ['slug' => $plan->slug])->assertStatus(200);

        // Sin consentimiento: cesta abandonada, pero sin email.
        $this->postWebhook('checkout.session.expired', [
            'id' => 'cs_a', 'object' => 'checkout.session', 'customer_details' => ['email' => 'sin@example.test'],
            'consent' => ['promotions' => 'opt_out'], 'after_expiration' => ['recovery' => ['url' => 'https://pay.stripe.test/r/a']],
        ])->assertStatus(200);
        // Con consentimiento: email de recuperación con el enlace de Stripe.
        $this->postWebhook('checkout.session.expired', [
            'id' => 'cs_b', 'object' => 'checkout.session', 'customer_details' => ['email' => 'con@example.test'],
            'consent' => ['promotions' => 'opt_in'], 'after_expiration' => ['recovery' => ['url' => 'https://pay.stripe.test/r/b']],
        ])->assertStatus(200);

        $a = PackCheckoutAttempt::where('stripe_session_id', 'cs_a')->first();
        $b = PackCheckoutAttempt::where('stripe_session_id', 'cs_b')->first();
        $this->assertSame('expired', $a->status);
        $this->assertNull($a->recovery_email_sent_at);
        $this->assertNotNull($b->recovery_email_sent_at);
        Mail::assertQueued(PackRecoveryMail::class, 1);
        Mail::assertQueued(PackRecoveryMail::class, fn ($m) => $m->hasTo('con@example.test'));

        // Paga con el enlace de recuperación (sesión nueva) → intento recuperado.
        $this->postWebhook('checkout.session.completed', [
            'id' => 'cs_recovery', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 4900, 'currency' => 'eur',
            'payment_intent' => 'pi_r', 'customer_details' => ['email' => 'con@example.test'], 'metadata' => ['plan_id' => (string) $plan->id],
        ])->assertStatus(200);
        $this->assertSame('recovered', $b->fresh()->status);
    }

    // ═══ Panel ════════════════════════════════════════════════════════

    public function test_admin_sees_analytics_funnel_and_exports_newsletter(): void
    {
        $plan = $this->makePack();
        foreach (['aaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbb'] as $hash) {
            $this->withHeaders($this->web())->postJson('/api/track', [
                'path' => '/packs/gluteo-12', 'device' => 'mobile',
                'attribution' => ['visitor_hash' => $hash, 'utm_source' => 'meta', 'utm_campaign' => 'reels', 'click_id_type' => 'fbclid'],
            ])->assertStatus(200);
        }
        $this->withHeaders($this->web())->postJson('/api/track', ['path' => '/home', 'attribution' => ['visitor_hash' => 'cccccccccccccccc']]);
        $this->fakeStripe('cs_f');
        $this->postJson('/api/pack-checkout', ['slug' => $plan->slug, 'attribution' => ['visitor_hash' => 'aaaaaaaaaaaaaaaa']]);
        NewsletterSubscriber::create(['email' => 'c@example.test', 'status' => 'confirmed', 'confirm_token' => 't1', 'unsubscribe_token' => 'u1', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'p@example.test', 'status' => 'pending', 'confirm_token' => 't2', 'unsubscribe_token' => 'u2']);

        $this->actingAsAdmin();
        $data = $this->getJson('/api/admin/web-analytics')->assertStatus(200)->json('data');

        $this->assertSame(3, $data['totals']['visitors']);
        $this->assertSame(2, $data['totals']['from_ads']);
        $this->assertSame(1, $data['totals']['checkout_started']);
        $campaign = collect($data['campaigns'])->firstWhere('utm_campaign', 'reels');
        $this->assertSame(2, $campaign['visitors']);
        $this->assertSame(1, $campaign['checkout_started']);
        $pack = collect($data['packs'])->firstWhere('slug', 'gluteo-12');
        $this->assertSame(2, $pack['landing_visitors']);

        $this->getJson('/api/admin/checkout-attempts')->assertStatus(200)->assertJsonPath('summary.in_progress', 1);
        $this->getJson('/api/admin/newsletter-stats')->assertStatus(200)->assertJsonPath('data.confirmed', 1);

        $csv = $this->get('/api/admin/newsletter-export')->assertStatus(200)->getContent();
        $this->assertStringContainsString('c@example.test', $csv);
        $this->assertStringNotContainsString('p@example.test', $csv);
    }

    public function test_contact_inbox_hides_archived_and_counts_unread(): void
    {
        $base = ['name' => 'X', 'email' => 'x@example.test', 'message' => 'Hola'];
        $new = ContactMessage::create($base + ['status' => 'new']);
        ContactMessage::create($base + ['status' => 'archived']);

        $this->actingAsAdmin();
        $this->getJson('/api/admin/contact-messages?status=inbox')->assertStatus(200)
            ->assertJsonCount(1, 'data')->assertJsonPath('unread', 1);

        $this->postJson('/api/admin/contact-messages-update', ['id' => $new->id, 'status' => 'read'])->assertStatus(200);
        $this->assertNotNull($new->fresh()->read_at);
        $this->getJson('/api/admin/contact-messages')->assertJsonCount(2, 'data')->assertJsonPath('unread', 0);
    }

    public function test_marketing_admin_endpoints_require_admin(): void
    {
        $this->getJson('/api/admin/web-analytics')->assertStatus(401);
        $this->getJson('/api/admin/newsletter-subscribers')->assertStatus(401);
    }
}
