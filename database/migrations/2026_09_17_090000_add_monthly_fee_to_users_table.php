<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tarifa mensual fija de un cliente de entrenamiento personal
// (is_personal_client=true), usada como precio por defecto en el
// seguimiento manual de pagos del admin (ver
// subscription_payment_records / SubscriptionPaymentController). No
// tiene relación con los planes automáticos de Plan/PlanSubscription
// (esos ya llevan su propio precio) -- esto es solo para clientes que
// pagan fuera de la pasarela (transferencia, efectivo) y el coach marca
// el pago a mano cada mes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_fee', 8, 2)->nullable()->after('is_personal_client');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_fee');
        });
    }
};
