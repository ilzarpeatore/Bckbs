<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.1).
     * Decisión de diseño propia: a diferencia de ajustar_carga_pct/etc.
     * (que ya tenían proposed_weight/proposed_reps), la acción
     * sustituir_ejercicio necesita proponer un EJERCICIO distinto, no un
     * valor numérico — sin esta columna no había forma de representar esa
     * propuesta en next_session_targets. Nullable y aditiva: no afecta a
     * ninguna fila existente ni a ninguna acción que no sea sustitución.
     */
    public function up(): void
    {
        Schema::table('next_session_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('proposed_exercise_id')->nullable()->after('proposed_reps');
            $table->foreign('proposed_exercise_id', 'nst_proposed_exercise_fk')
                ->references('id')->on('exercises')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('next_session_targets', function (Blueprint $table) {
            $table->dropForeign('nst_proposed_exercise_fk');
            $table->dropColumn('proposed_exercise_id');
        });
    }
};
