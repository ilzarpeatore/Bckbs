<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 14 ítem
 * 43 (docs/Motor_Autorregulacion_Analisis.md): hasta ahora un `logic_group`
 * exigía AND estricto de TODAS sus condiciones. `min_condiciones_requeridas`
 * es opcional (nullable) y se configura en cualquiera de las condiciones
 * del grupo (SessionProgressionRuleEngine::resolveMinCondicionesRequeridas()
 * toma el máximo configurado en el grupo) -- sin configurar en ninguna, el
 * grupo se sigue evaluando como AND estricto (comportamiento sin cambios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_progression_rule_conditions', function (Blueprint $table) {
            $table->unsignedInteger('min_condiciones_requeridas')->nullable()->after('logic_group');
        });
    }

    public function down(): void
    {
        Schema::table('session_progression_rule_conditions', function (Blueprint $table) {
            $table->dropColumn('min_condiciones_requeridas');
        });
    }
};
