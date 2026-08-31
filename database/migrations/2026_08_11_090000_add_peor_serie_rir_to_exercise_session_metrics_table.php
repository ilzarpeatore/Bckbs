<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1,
     * progression_rule_conditions.variable = 'peor_serie').
     *
     * Fase 1 ya calcula el RIR del peor set (`$peorSerieRir` dentro de
     * SessionInterpretationService::aggregateSetLogs()) pero solo persistía
     * el índice (`peor_serie_index`) porque en Fase 1 nada lo necesitaba
     * todavía. La condición `peor_serie` del motor de reglas (Fase 2) tiene
     * que comparar un valor numérico real (gte/lte/between) contra el RIR
     * del peor set — y el motor tiene prohibido leer `client_exercise_logs`
     * directamente (regla explícita del documento), así que el índice solo
     * no basta.
     *
     * Columna aditiva, nullable, sin tocar ninguna columna ni lógica
     * existente de Fase 1 — no rompe nada ya verificado, solo añade el dato
     * que Fase 2 necesita para poder consumir exclusivamente
     * exercise_session_metrics tal como exige el documento.
     */
    public function up(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->decimal('peor_serie_rir', 5, 2)->nullable()->after('peor_serie_index');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->dropColumn('peor_serie_rir');
        });
    }
};
