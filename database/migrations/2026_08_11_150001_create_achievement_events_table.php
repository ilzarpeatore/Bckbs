<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2, tarea
     * #20). Capa de FEED/historial de logros — NO recalcula PRs (decisión
     * de reconciliación ya tomada con el usuario): personal_records +
     * ClientExerciseLogObserver::notifyNewRecord() siguen siendo la fuente
     * de verdad y la notificación real, sin gate, para todos los clientes.
     * Esta tabla se escribe ADEMÁS de eso, solo para paid-tier, ver
     * ClientExerciseLogObserver / ClientCalendarController::finishSession()
     * / EvaluateSessionAchievements.
     *
     * source_type/source_id: referencia polimórfica flexible al origen
     * (columna libre, sin FK — apunta a distintas tablas según el tipo):
     * 'App\Models\PersonalRecord' para pr_carga/mejora_e1rm,
     * 'App\Models\ClientExerciseLog' para pr_reps (no respaldado por
     * personal_records, ver decisión en ClientExerciseLogObserver),
     * null para racha_sesiones/hito_compliance/progreso_sesion (no hay un
     * único modelo de origen natural para esos tipos).
     */
    public function up(): void
    {
        Schema::create('achievement_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('type'); // App\Enums\AchievementEventType
            $table->unsignedBigInteger('exercise_id')->nullable();
            $table->decimal('value', 10, 2)->nullable();
            $table->decimal('previous_best', 10, 2)->nullable();
            $table->boolean('significancia_verificada')->default(true);
            $table->boolean('shown_to_client')->default(false);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');

            $table->index(['client_id', 'type', 'created_at'], 'achievement_events_client_type_idx');
            $table->index(['client_id', 'shown_to_client'], 'achievement_events_client_shown_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_events');
    }
};
