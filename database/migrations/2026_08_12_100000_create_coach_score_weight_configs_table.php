<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
 * §0.2/§5). Investigado antes de crear esto: ReadinessCalculationService
 * guarda sus pesos en config/readiness.php (archivo estático, sin tabla,
 * SIN granularidad por coach) — no hay ningún patrón de tabla reutilizable
 * ya construido. Esta feature sí necesita pesos por coach (+ un toggle de
 * reenganche automático, también por coach), así que se crea la tabla
 * genérica que el propio documento proponía como plan B, en vez de una
 * tabla de un solo uso — deliberadamente NO se migra ReadinessCalculationService
 * a esto (fuera de alcance, cero riesgo de regresión en el motor ya
 * verificado). `id` bigint autoincrement (no uuid) — mismo criterio ya
 * documentado en coach_exception_items: ninguna tabla nueva del Motor usa
 * uuid, mantener la convención real del esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_score_weight_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            // Hoy solo 'retention_risk' -- columna genérica por si en el
            // futuro se decide migrar readiness a este mismo patrón, sin
            // forzar un enum de un solo valor real todavía.
            $table->string('score_type', 40);
            // Nullable a propósito: null = usar los valores por defecto del
            // servicio (documento §3), no 0 -- un coach que nunca configuró
            // nada no debe tener pesos en cero.
            $table->decimal('w1', 4, 3)->nullable();
            $table->decimal('w2', 4, 3)->nullable();
            $table->decimal('w3', 4, 3)->nullable();
            $table->decimal('w4', 4, 3)->nullable();
            $table->boolean('auto_reengagement_enabled')->default(true);
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['coach_id', 'score_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_score_weight_configs');
    }
};
