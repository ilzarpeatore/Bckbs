<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 4 (readiness score).
     * Output diario de ReadinessCalculationService (documento §4.1).
     *
     * NOTA DE NOMBRES DE COLUMNA: el documento original propone
     * "sueño_z_score" (con ñ) — se normaliza a "sueno_z_score" (ASCII) para
     * ser consistente con el resto del esquema real, que evita acentos en
     * identificadores de columna sistemáticamente (ver "localizacion",
     * "intensidad", "momento" en pain_reports, nunca con tilde) aunque el
     * propio concepto sea en español. Mismo criterio para "band" (valores
     * ASCII: optimo|reducido|bajo|dato_insuficiente).
     *
     * unique(client_id, date): el job diario es idempotente — reprocesar
     * el mismo día (ej. porque llegaron datos de Health con delay)
     * actualiza la fila existente en vez de duplicar.
     */
    public function up(): void
    {
        Schema::create('readiness_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('date');
            $table->decimal('hrv_z_score', 8, 4)->nullable();
            $table->decimal('sueno_z_score', 8, 4)->nullable();
            $table->decimal('subjetivo_score', 8, 4)->nullable();
            $table->decimal('acwr', 8, 4)->nullable();
            $table->decimal('combined_score', 8, 4)->nullable();
            $table->string('band'); // optimo | reducido | bajo | dato_insuficiente
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['client_id', 'date'], 'readiness_scores_client_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_scores');
    }
};
