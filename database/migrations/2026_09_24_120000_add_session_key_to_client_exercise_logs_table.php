<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * session_key (2026-09-24): identificador de sesión que genera la app al
     * empezar un entrenamiento y manda en cada logSets().
     *
     * Hasta ahora una "sesión" se deducía de program_day_assignment_id o,
     * en entrenamientos sueltos (sin día de calendario), del día
     * (performed_date) -- ver ClientExerciseLog::scopeLatestSnapshots().
     * Dos entrenamientos sueltos del mismo ejercicio el mismo día (mañana y
     * tarde) se fundían en uno solo y se perdía el primero. Con session_key
     * cada sesión tiene su propia "última foto".
     *
     * Nullable: las filas históricas (y las de versiones viejas de la app)
     * no lo tienen y siguen agrupándose como siempre. Índice compuesto con
     * client_id porque todas las consultas de snapshots van por cliente.
     * Solo schema builder, sin SQL crudo (portable sqlite/MySQL).
     */
    public function up(): void
    {
        Schema::table('client_exercise_logs', function (Blueprint $table) {
            $table->string('session_key', 64)->nullable()->after('program_day_assignment_id');
            $table->index(['client_id', 'session_key']);
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_logs', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'session_key']);
            $table->dropColumn('session_key');
        });
    }
};
