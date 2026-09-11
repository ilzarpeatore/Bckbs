<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 13 ítem
 * 40 (docs/Motor_Autorregulacion_Analisis.md): el redondeo de una carga
 * propuesta usaba siempre el `RoundingMode` genérico de la regla
 * (nearest_1kg/nearest_2_5kg/none), sin relación con el incremento real
 * del equipo del ejercicio (mancuernas ±2kg, máquina con saltos de 5kg,
 * barra con discos de 1.25kg) -- podía proponer un peso no cargable en la
 * práctica. Nullable: sin configurar, cae al RoundingMode de la regla
 * exactamente como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->decimal('increment_kg', 5, 2)->nullable()->after('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('increment_kg');
        });
    }
};
