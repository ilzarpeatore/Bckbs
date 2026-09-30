<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packs vendidos en la web (2026-09-30): un Plan ya lleva programa de
 * entrenamiento + plan de nutrición y los asigna solo al concederse
 * (PlanFulfillmentService). Se completa con hábitos y recursos, datos para
 * mostrarlo en la web, y una tabla de compras que conecta el pago en Stripe
 * (hecho en la web, sin cuenta) con la cuenta de la app: por email o por un
 * código de canje. Ver docs/PACKS_WEB.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('sold_on_web')->default(false)->after('is_active');
            $table->string('short_description', 255)->nullable()->after('description');
            $table->string('image_url')->nullable()->after('short_description');
            // Plantillas de hábitos (habits con client_id NULL) y recursos que el pack asigna.
            $table->json('habit_template_ids')->nullable()->after('meal_plan_template_id');
            $table->json('resource_ids')->nullable()->after('habit_template_ids');
        });

        Schema::create('pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('email');
            $table->string('customer_name')->nullable();
            $table->string('stripe_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            // paid -> pagado, sin cuenta vinculada todavía; claimed -> vinculado
            // a una cuenta (pack activado o pendiente de terminar el onboarding);
            // refunded -> devuelto, acceso retirado.
            $table->string('status', 20)->default('paid');
            $table->string('redeem_code', 20)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('plan_subscription_id')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_purchases');
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['sold_on_web', 'short_description', 'image_url', 'habit_template_ids', 'resource_ids']);
        });
    }
};
