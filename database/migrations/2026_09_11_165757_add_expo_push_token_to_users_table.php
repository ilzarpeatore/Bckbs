<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones push via Expo (decision 2026-09-11: mas barato que terminar
 * de integrar OneSignal desde cero -- el cliente ya tiene expo-notifications
 * instalado, solo hace falta registrar el push token). Columna hermana de
 * 'player_id' (OneSignal, nunca llego a usarse de verdad), mismo patron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('expo_push_token')->nullable()->after('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('expo_push_token');
        });
    }
};
