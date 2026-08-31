<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeEventIdToPlanSubscriptionsTable extends Migration
{
    /**
     * Idempotencia del webhook de Stripe: si el mismo evento se reintenta
     * (Stripe reenvía si no responde 2xx a tiempo), firstOrCreate() por
     * stripe_event_id evita crear una PlanSubscription duplicada.
     */
    public function up()
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->string('stripe_event_id')->nullable()->unique()->after('payment_method');
        });
    }

    public function down()
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_event_id');
        });
    }
}
