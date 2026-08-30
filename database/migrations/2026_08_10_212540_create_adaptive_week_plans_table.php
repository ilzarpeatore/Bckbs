<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 4 (modo vida real / plan
     * adaptativo, documento §4.2). AdaptiveWeekPlanner SIEMPRE crea filas
     * con status=propuesto — nunca se aplica sola. La reducción real (vía
     * client_exercise_overrides, tabla ya existente) solo se escribe cuando
     * el coach aprueba explícitamente (endpoint de aprobación).
     *
     * Nombre de columna normalizado a ASCII por consistencia con el resto
     * del esquema (ver nota equivalente en readiness_scores): "priorización"
     * del documento -> "priorizacion".
     */
    public function up(): void
    {
        Schema::create('adaptive_week_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('original_week_start');
            $table->unsignedTinyInteger('sessions_available');
            // mantener_ejercicios_principales | mantener_grupo_muscular_prioritario | mantener_distribucion_semanal_completa
            $table->string('priorizacion');
            $table->boolean('mesocycle_extension')->default(false);
            $table->string('status')->default('propuesto'); // propuesto | aprobado | aplicado
            // AÑADIDO (AdaptiveWeekPlanner): snapshot de la propuesta calculada
            // -- qué program_day_assignments se mantienen/eliminan y qué
            // workout_template_exercise_ids se recortan como accesorios en las
            // sesiones que sí se mantienen. Necesario porque el propio
            // AdaptiveWeekPlan no tiene forma de "recordar" la propuesta exacta
            // sin esto, y una futura transición aprobado->aplicado (fuera del
            // alcance de esta tarea, ver AdaptiveWeekPlanner) la necesitaría tal
            // cual para escribir los ClientExerciseOverride reales sin
            // recalcular (el cálculo podría dar un resultado distinto si el
            // programa cambió entretanto).
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['client_id', 'original_week_start'], 'adaptive_week_plans_client_week_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_week_plans');
    }
};
