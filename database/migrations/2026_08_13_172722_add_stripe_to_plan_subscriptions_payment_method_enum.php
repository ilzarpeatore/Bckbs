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
        // FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): "MODIFY
        // ... ENUM(...)" es solo-MySQL y sqlite no tiene tipo ENUM real -- el
        // ->enum() de Laravel (ver 2026_08_05_100010_add_payment_columns_to_plan_subscriptions.php)
        // se traduce en sqlite a una columna sin CHECK de valores permitidos,
        // así que no hay ninguna restricción que ampliar ahí: no-op fuera de
        // MySQL. MySQL en producción no cambia de camino.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE plan_subscriptions MODIFY payment_method ENUM('bizum','efectivo','transferencia','otro','stripe') NULL");
    }

    public function down()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE plan_subscriptions MODIFY payment_method ENUM('bizum','efectivo','transferencia','otro') NULL");
    }
}
