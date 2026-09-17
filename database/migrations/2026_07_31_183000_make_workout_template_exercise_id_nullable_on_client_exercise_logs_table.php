<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite registrar ejercicios anadidos ad-hoc durante la sesion
     * (boton "Anadir ejercicio +" en workout_session_screen.tsx, antes
     * solo mostraba un aviso de "no disponible") - sin WorkoutTemplateExercise
     * que los respalde, ya que no forman parte de la plantilla del coach.
     *
     * FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): "MODIFY"
     * es solo-MySQL -- en sqlite (tests locales) se usa ->change() nativo
     * de Laravel 11 para el mismo resultado, MySQL en producción no cambia.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('client_exercise_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('workout_template_exercise_id')->nullable()->change();
            });
            return;
        }

        DB::statement('ALTER TABLE client_exercise_logs MODIFY workout_template_exercise_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('client_exercise_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('workout_template_exercise_id')->nullable(false)->change();
            });
            return;
        }

        DB::statement('ALTER TABLE client_exercise_logs MODIFY workout_template_exercise_id BIGINT UNSIGNED NOT NULL');
    }
};
