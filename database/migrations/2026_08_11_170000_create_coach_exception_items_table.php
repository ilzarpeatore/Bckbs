<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Panel de Excepciones del Coach — capa de índice/feed sobre datos que
     * el Motor de Auto-Regulación ya genera (pain_reports, next_session_
     * targets, readiness_scores, adaptive_week_plans). No sustituye a esas
     * tablas origen.
     *
     * DESVIACIONES DEL DOCUMENTO ORIGINAL (docs/Panel_Excepciones_
     * Implementacion.md), decididas para mantener consistencia con el
     * resto del esquema real del Motor:
     * - `id` bigint autoincrement, no uuid — NINGUNA tabla nueva del Motor
     *   (exercise_session_metrics, next_session_targets, achievement_events,
     *   etc.) usa uuid como PK; introducir uuid solo aquí rompería esa
     *   convención sin ninguna razón técnica declarada en el documento.
     * - `source_id` unsignedBigInteger, no uuid — los modelos origen reales
     *   (PainReport, NextSessionTarget, ReadinessScore, AdaptiveWeekPlan)
     *   usan todos id autoincrement, igual que hace `achievement_events.
     *   source_id` para el mismo patrón de referencia polimórfica libre.
     * - timestamps() completos (created_at + updated_at), no solo
     *   created_at como pedía el documento — a diferencia de override_logs
     *   (log de auditoría de solo-inserción, con razón documentada para
     *   omitir updated_at), esta tabla SÍ se actualiza en el tiempo
     *   (status pendiente -> resuelta/descartada, resolved_at/resolved_by),
     *   por lo que updated_at es información real, no ruido.
     */
    public function up(): void
    {
        Schema::create('coach_exception_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('client_id');
            $table->string('category', 40);
            $table->string('severity', 10);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pendiente');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['coach_id', 'status', 'severity'], 'cei_coach_status_severity_idx');
            // Idempotencia: evita duplicar el mismo ítem si el listener que
            // lo origina se dispara dos veces (mismo criterio que
            // exercise_session_metrics/health_data_points). NULLs no
            // colisionan entre sí en MySQL -- inactividad (sin source) no
            // se ve afectada, su propio anti-duplicado vive en
            // CoachExceptionFeedService::hasPendingForClientCategory().
            $table->unique(['source_type', 'source_id'], 'cei_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_exception_items');
    }
};
