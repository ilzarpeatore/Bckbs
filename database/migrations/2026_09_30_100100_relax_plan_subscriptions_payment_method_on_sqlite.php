<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_08_13_172722 añadió 'stripe' al enum payment_method solo en MySQL,
 * suponiendo que en sqlite el enum no tenía CHECK. Sí lo tiene (Laravel lo
 * crea), así que en sqlite (tests) 'stripe' seguía rechazado. Aquí se pasa a
 * string solo en sqlite; MySQL (producción) ya admite 'stripe' y no cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás: el enum original no admitía 'stripe' en sqlite.
    }
};
