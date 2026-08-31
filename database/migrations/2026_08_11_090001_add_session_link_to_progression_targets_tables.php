<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 2, decisión de diseño propia
     * (no especificada literalmente en el documento, que solo dice
     * "Jobs asíncronos idempotentes" y "escribir resultado en
     * next_session_targets" sin más detalle de esquema para ese punto).
     *
     * Sin esta columna, EvaluateSessionProgressionRules (job que dispara
     * evaluateForExercise() por cada ejercicio tras cerrar sesión) no
     * tendría forma de saber, en un reintento, si ya generó el target para
     * esa sesión concreta -> duplicaría filas en next_session_targets /
     * shadow_evaluations en cada reintento, violando la regla explícita
     * "todos los jobs asíncronos deben ser idempotentes" (documento,
     * Notas generales).
     *
     * También resuelve otro problema real: GET
     * /api/exercises/{id}/progression-history no tenía ninguna forma de
     * correlacionar un next_session_target con la sesión/ejercicio que lo
     * originó (antes solo client_id+exercise_id+generated_at, sin vínculo
     * real). Con esta columna ambos problemas se resuelven con un único
     * cambio aditivo.
     *
     * Nullable a propósito: approve/edit/reject sobre una sugerencia ya
     * existente no cambian esta columna (siguen apuntando a la sesión
     * original que la generó), y no rompe nada de lo ya migrado.
     */
    public function up(): void
    {
        Schema::table('next_session_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('workout_session_review_id')->nullable()->after('exercise_id');
            $table->foreign('workout_session_review_id', 'nst_wsr_fk')
                ->references('id')->on('workout_session_reviews')->onDelete('set null');
            // NULL no colisiona consigo mismo en MySQL -> filas sin sesión
            // (si las hubiera en el futuro) no rompen la unicidad; filas
            // con sesión real quedan protegidas de duplicados por reintento.
            $table->unique(['workout_session_review_id', 'exercise_id', 'client_id'], 'nst_session_exercise_client_unique');
        });

        Schema::table('shadow_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('workout_session_review_id')->nullable()->after('exercise_id');
            $table->foreign('workout_session_review_id', 'se_wsr_fk')
                ->references('id')->on('workout_session_reviews')->onDelete('set null');
            $table->unique(['workout_session_review_id', 'exercise_id', 'client_id'], 'se_session_exercise_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('next_session_targets', function (Blueprint $table) {
            $table->dropUnique('nst_session_exercise_client_unique');
            $table->dropForeign('nst_wsr_fk');
            $table->dropColumn('workout_session_review_id');
        });

        Schema::table('shadow_evaluations', function (Blueprint $table) {
            $table->dropUnique('se_session_exercise_client_unique');
            $table->dropForeign('se_wsr_fk');
            $table->dropColumn('workout_session_review_id');
        });
    }
};
