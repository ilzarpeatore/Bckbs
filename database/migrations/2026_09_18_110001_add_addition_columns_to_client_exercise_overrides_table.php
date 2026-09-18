<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría 2026-09-18 (bug real de aislamiento de datos):
 * client_exercise_overrides ya permite ocultar/ajustar un ejercicio que
 * SÍ existe en la plantilla compartida (workout_template_exercise_id
 * obligatorio hasta ahora), pero no representa un ejercicio que un coach
 * añade solo para un cliente concreto -- eso obligaba a
 * SessionDetailController::addExercise()/addBlock() a crear filas
 * directamente en workout_template_exercises/workout_template_blocks,
 * mutando la plantilla compartida y afectando a cualquier otro cliente
 * que la usara.
 *
 * Una fila de esta tabla ahora significa una de dos cosas:
 * - Override de un ejercicio real: workout_template_exercise_id NOT NULL
 *   (comportamiento de siempre, sin cambios).
 * - Ejercicio añadido solo para este cliente: workout_template_exercise_id
 *   NULL, exercise_id NOT NULL, y exactamente uno de
 *   workout_template_block_id (se añade a un bloque de la plantilla
 *   compartida) / client_block_override_id (se añade a un bloque propio
 *   del cliente, ver client_block_overrides).
 *
 * workout_template_exercise_id pasa a nullable con SQL directo, no
 * ->change(), mismo motivo que 2026_07_16_110001_make_workout_id_nullable...
 * y 2026_08_04_150000_add_client_features_to_habits_table.php: requiere
 * doctrine/dbal, que no está en composer.lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE client_exercise_overrides MODIFY workout_template_exercise_id BIGINT UNSIGNED NULL');

        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->unsignedBigInteger('workout_template_block_id')->nullable()->after('workout_template_exercise_id');
            $table->unsignedBigInteger('client_block_override_id')->nullable()->after('workout_template_block_id');
            $table->unsignedBigInteger('exercise_id')->nullable()->after('client_block_override_id');
            $table->unsignedInteger('sequence')->nullable()->after('exercise_id');

            $table->foreign('workout_template_block_id')->references('id')->on('workout_template_blocks')->onDelete('cascade');
            $table->foreign('client_block_override_id')->references('id')->on('client_block_overrides')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->dropForeign(['workout_template_block_id']);
            $table->dropForeign(['client_block_override_id']);
            $table->dropForeign(['exercise_id']);
            $table->dropColumn(['workout_template_block_id', 'client_block_override_id', 'exercise_id', 'sequence']);
        });

        DB::statement('ALTER TABLE client_exercise_overrides MODIFY workout_template_exercise_id BIGINT UNSIGNED NOT NULL');
    }
};
