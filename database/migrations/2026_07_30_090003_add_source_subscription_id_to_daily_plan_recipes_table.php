<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad de qué Subscription (compra de un Package) generó esta
     * entrada de calendario — distinto de assigned_by_user_id (que dice
     * QUIÉN la asignó, no DE QUÉ COMPRA vino). Null = coach/cliente manual,
     * no ligado a ningún paquete. Necesario para poder retirar del calendario
     * solo las entradas futuras de un paquete concreto cuando expira, sin
     * tocar las de otros paquetes activos en paralelo ni las asignadas a mano.
     */
    public function up(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->unsignedBigInteger('source_subscription_id')->nullable()->after('assigned_by_user_id');
            $table->foreign('source_subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->dropForeign(['source_subscription_id']);
            $table->dropColumn('source_subscription_id');
        });
    }
};
