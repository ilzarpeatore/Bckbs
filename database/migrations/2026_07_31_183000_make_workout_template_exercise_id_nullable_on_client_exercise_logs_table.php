<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permite registrar ejercicios anadidos ad-hoc durante la sesion
     * (boton "Anadir ejercicio +" en workout_session_screen.tsx, antes
     * solo mostraba un aviso de "no disponible") - sin WorkoutTemplateExercise
     * que los respalde, ya que no forman parte de la plantilla del coach.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE client_exercise_logs MODIFY workout_template_exercise_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE client_exercise_logs MODIFY workout_template_exercise_id BIGINT UNSIGNED NOT NULL');
    }
};
