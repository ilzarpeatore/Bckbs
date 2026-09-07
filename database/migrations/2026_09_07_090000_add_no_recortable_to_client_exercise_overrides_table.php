<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Fase 4b, Ronda 4 ítem 13
 * (docs/Motor_Autorregulacion_Analisis.md). Permite marcar un accesorio
 * como "no recortable" (p. ej. trabajo de rehabilitación prescrito por
 * dolor) para que `AdaptiveWeekPlanner::accessoryExerciseIdsToTrim()` lo
 * respete al proponer qué recortar en una semana adaptativa.
 *
 * Vive en `client_exercise_overrides` (no en una tabla nueva) porque ya es
 * la tabla natural para "override por cliente + program_day_assignment +
 * ejercicio de plantilla" -- el mismo concepto que ya usa `hidden` para el
 * lado opuesto (ocultar/recortar). `no_recortable` y `hidden` son
 * independientes: un coach puede marcar un ejercicio como no recortable
 * sin necesidad de que ya esté oculto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->boolean('no_recortable')->default(false)->after('hidden');
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->dropColumn('no_recortable');
        });
    }
};
