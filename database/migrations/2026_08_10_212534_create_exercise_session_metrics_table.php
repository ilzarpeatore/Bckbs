<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 1 (Capa 2: interpretación).
     * Output de SessionInterpretationService — una fila por ejercicio+sesión.
     * "Sesión" = workout_session_reviews.id (fila ya creada por
     * ClientCalendarController::finishSession()), en vez de inventar un
     * session_id nuevo — es el identificador real de sesión que ya existe.
     * Ningún componente de Fase 2 en adelante debe leer client_exercise_logs
     * directamente, siempre a través de esta tabla (regla del documento).
     */
    public function up(): void
    {
        Schema::create('exercise_session_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_session_review_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedBigInteger('client_id');

            $table->decimal('rir_delta_sesion', 6, 2)->nullable();
            $table->decimal('completion_ratio', 5, 2)->nullable();
            // Índice (0-based) dentro de logged_sets del set con menor RIR
            // reportado — no existe una tabla set_logs con id propio que
            // referenciar (decisión ya confirmada, el JSON no se normaliza).
            $table->unsignedSmallInteger('peor_serie_index')->nullable();
            $table->decimal('carga_efectiva', 8, 2)->nullable();
            $table->boolean('is_outlier')->default(false);
            $table->boolean('sin_dato_suficiente')->default(false);
            $table->decimal('tendencia_rir', 6, 3)->nullable();
            $table->unsignedInteger('sesiones_consecutivas_sin_cambio')->default(0);
            $table->decimal('e1rm_estimado', 8, 2)->nullable();
            // Poblado por el motor de reglas en Fase 2 (no existe todavía
            // "última acción del motor" en Fase 1) — queda a 0 hasta entonces.
            $table->unsignedInteger('racha_misma_direccion')->default(0);
            $table->boolean('blocked_by_pain')->default(false);
            $table->timestamps();

            $table->foreign('workout_session_review_id')->references('id')->on('workout_session_reviews')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            // Idempotencia del job: reprocesar la misma sesión no debe duplicar filas.
            $table->unique(['workout_session_review_id', 'exercise_id'], 'esm_session_exercise_unique');
            $table->index(['client_id', 'exercise_id', 'created_at'], 'esm_client_exercise_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_session_metrics');
    }
};
