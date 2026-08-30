<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkoutTemplateExerciseIdToExerciseSessionMetricsTable extends Migration
{
    /**
     * Fix real: SessionProgressionRuleEngine::resolveLastPrescribed()
     * resolvia el WorkoutTemplateExercise re-buscandolo por
     * (workout_template_id, exercise_id) sin desambiguar bloques - si el
     * mismo ejercicio aparecia en mas de un bloque de la plantilla podia
     * coger la prescripcion equivocada. El motor tiene prohibido leer
     * client_exercise_logs directamente (regla del documento, ver
     * cabecera de SessionProgressionRuleEngine.php), asi que el slot
     * exacto se resuelve una vez en Fase 1 (SessionInterpretationService,
     * que si lee logs) y se persiste aqui para que Fase 2 lo lea sin
     * ambiguedad.
     */
    public function up()
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->foreignId('workout_template_exercise_id')
                ->nullable()
                ->after('exercise_id')
                ->constrained('workout_template_exercises')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workout_template_exercise_id');
        });
    }
}
