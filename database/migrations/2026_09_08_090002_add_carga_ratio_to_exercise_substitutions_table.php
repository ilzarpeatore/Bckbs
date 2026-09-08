<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 11 ítem
 * 32 (docs/Motor_Autorregulacion_Analisis.md): hasta ahora una sustitución
 * de ejercicio propuesta nunca traía carga de arranque
 * (`finalizeSubstitution()` dejaba `proposed_weight`/`proposed_reps` en
 * null siempre) -- el coach tenía que calcular desde cero con cuánto peso
 * empezar el sustituto. `carga_ratio` es opcional (nullable): sin ratio
 * configurado, el comportamiento no cambia (sigue sin proponer carga).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_substitutions', function (Blueprint $table) {
            $table->decimal('carga_ratio', 5, 3)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_substitutions', function (Blueprint $table) {
            $table->dropColumn('carga_ratio');
        });
    }
};
