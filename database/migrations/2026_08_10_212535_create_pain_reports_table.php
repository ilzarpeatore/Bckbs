<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloqueo por dolor (Fase 1, §1.3 del documento) — sin gate de tier,
     * aplica a todos los clientes. Se registra DURANTE la sesión (antes de
     * que exista workout_session_reviews, que se crea recién al cerrar la
     * sesión) — por eso identifica la sesión igual que
     * ClientCalendarController::finishSession() ya hace: por
     * program_day_assignment_id (día de programa) o workout_template_id
     * (workout suelto), nunca por un session_id inventado.
     */
    public function up(): void
    {
        Schema::create('pain_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedBigInteger('program_day_assignment_id')->nullable();
            $table->unsignedBigInteger('workout_template_id')->nullable();
            $table->unsignedSmallInteger('set_number')->nullable();
            $table->string('tipo'); // molestia_leve | dolor_agudo | dolor_que_empeora_durante_sesion
            $table->string('localizacion'); // tag corporal predefinido
            $table->unsignedTinyInteger('intensidad'); // 1-5
            $table->string('momento'); // al_iniciar | durante_ejecucion | al_finalizar | al_dia_siguiente
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->foreign('program_day_assignment_id')->references('id')->on('program_day_assignments')->onDelete('set null');
            $table->foreign('workout_template_id')->references('id')->on('workout_templates')->onDelete('set null');

            $table->index(['client_id', 'exercise_id', 'localizacion', 'created_at'], 'pain_reports_pattern_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pain_reports');
    }
};
