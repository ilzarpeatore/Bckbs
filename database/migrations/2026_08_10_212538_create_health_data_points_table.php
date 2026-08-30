<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 4 (readiness score).
     * Histórico crudo de lecturas de salud sincronizadas desde el
     * dispositivo (helper/health.ts) o introducidas a mano. No existía
     * ningún histórico persistido en backend hasta ahora — helper/health.ts
     * solo leía snapshots en vivo del dispositivo (documento §4.1).
     *
     * Idempotencia: updateOrCreate por (client_id, source, metric_type,
     * recorded_date) — si el cliente reenvía la lectura del mismo día
     * (ej. HealthKit corrige el valor de sueño más tarde), se actualiza en
     * vez de duplicar.
     */
    public function up(): void
    {
        Schema::create('health_data_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('source'); // apple_health | google_health | manual
            $table->string('metric_type'); // hrv | sleep_hours | resting_hr | steps
            $table->decimal('value', 10, 2);
            $table->date('recorded_date');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['client_id', 'source', 'metric_type', 'recorded_date'], 'health_data_points_unique');
            $table->index(['client_id', 'metric_type', 'recorded_date'], 'health_data_points_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_data_points');
    }
};
