<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (2026-10-01, ver docs/MARKETING_WEB.md):
 * - newsletter_subscribers: altas de la newsletter y la lista de espera de la
 *   web, con doble opt-in (RGPD) y la campaña de la que vino cada una.
 * - contact_messages: el formulario de contacto de la web (antes no se guardaba).
 * - web_pageviews: analítica propia SIN cookies (visitante = hash diario
 *   anónimo de IP + navegador, que la web calcula y nunca se guarda en claro).
 * - pack_checkout_attempts: cada vez que alguien pulsa "Comprar" en una
 *   landing; si la sesión de Stripe caduca sin pago, es una cesta abandonada.
 */
return new class extends Migration
{
    private function attribution(Blueprint $table): void
    {
        $table->string('visitor_hash', 32)->nullable()->index();
        $table->string('utm_source', 120)->nullable();
        $table->string('utm_medium', 120)->nullable();
        $table->string('utm_campaign', 160)->nullable()->index();
        $table->string('utm_content', 160)->nullable();
        $table->string('utm_term', 160)->nullable();
        $table->string('click_id_type', 16)->nullable(); // gclid, fbclid, ttclid, msclkid
        $table->string('referrer_host', 190)->nullable();
        $table->string('landing_path', 255)->nullable();
    }

    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('source', 60)->default('footer'); // footer, waitlist, pack:<slug>…
            $table->string('status', 20)->default('pending'); // pending, confirmed, unsubscribed
            $table->string('confirm_token', 64)->unique();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $this->attribution($table);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('subject', 190)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new'); // new, read, archived
            $table->timestamp('read_at')->nullable();
            $this->attribution($table);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('web_pageviews', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->index();
            $table->string('device', 10)->nullable(); // mobile, tablet, desktop
            $table->string('browser', 30)->nullable();
            $this->attribution($table);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('pack_checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('stripe_session_id')->unique();
            $table->string('email')->nullable()->index();
            // started; completed = pagó; expired = caducó sin pago (cesta
            // abandonada); recovered = caducó pero pagó después con el enlace
            // de recuperación.
            $table->string('status', 20)->default('started');
            $table->unsignedInteger('amount_cents')->nullable();
            $table->boolean('recovery_consent')->default(false);
            $table->string('recovery_url', 500)->nullable();
            $table->timestamp('recovery_email_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $this->attribution($table);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_checkout_attempts');
        Schema::dropIfExists('web_pageviews');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
