<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 13
 * ítems 38-39 (docs/Motor_Autorregulacion_Analisis.md):
 *
 * - `carga_efectiva_reps`: ya se calculaba en
 *   SessionInterpretationService::aggregateSetLogs() (reps de la serie que
 *   define carga_efectiva) pero nunca se persistía -- Fase 1 no lo
 *   necesitaba hasta ahora. Hace falta persistido para poder resolver
 *   `ConditionVariable::REPS_EN_TOPE_RANGO` en Fase 2 sin leer
 *   client_exercise_logs directamente (regla explícita del motor).
 * - `rir_delta_serie_top`: RIR delta de la PRIMERA serie completada de la
 *   sesión (orden cronológico dentro de logged_sets), distinto del
 *   promedio de toda la sesión (`rir_delta_sesion`) -- soporta razonar
 *   sobre la serie top por separado de las de backoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->unsignedTinyInteger('carga_efectiva_reps')->nullable()->after('carga_efectiva');
            $table->decimal('rir_delta_serie_top', 6, 2)->nullable()->after('rir_delta_sesion');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->dropColumn(['carga_efectiva_reps', 'rir_delta_serie_top']);
        });
    }
};
