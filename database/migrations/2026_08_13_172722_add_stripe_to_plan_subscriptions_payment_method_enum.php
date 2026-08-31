<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * payment_method (migración 2026_08_05_100010) es un enum pensado para que
 * el coach registre un pago manual en persona (bizum/efectivo/transferencia/
 * otro) — el webhook de Stripe necesita su propio valor real (no "otro",
 * para poder filtrar pagos de Stripe de verdad en
 * PlanSubscriptionController::transactions()).
 */
class AddStripeToPlanSubscriptionsPaymentMethodEnum extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE plan_subscriptions MODIFY payment_method ENUM('bizum','efectivo','transferencia','otro','stripe') NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE plan_subscriptions MODIFY payment_method ENUM('bizum','efectivo','transferencia','otro') NULL");
    }
}
