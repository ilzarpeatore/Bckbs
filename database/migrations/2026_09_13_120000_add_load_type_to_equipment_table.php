<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redondeo de carga por equipo (docs/Motor_Autorregulacion_Analisis.md,
 * hallazgo #4 / ítem 40): `exercises.increment_kg` ya cubre el fallback de
 * un incremento uniforme por ejercicio, pero no distingue el escalonado
 * real de una mancuerna (1kg hasta 15kg, luego 2.5kg hasta 50kg) de un
 * incremento uniforme de disco/máquina. `load_type` clasifica el equipo
 * para que `SessionProgressionRuleEngine::applyRounding()` elija la
 * función de redondeo correcta antes de mirar `increment_kg`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->enum('load_type', ['plate', 'dumbbell', 'fixed'])->nullable()->after('title');
        });

        DB::table('equipment')->whereIn('title', [
            'Barra olímpica', 'Máquina Smith', 'Polea', 'Leverage Machine',
        ])->update(['load_type' => 'plate']);

        DB::table('equipment')->where('title', 'Mancuernas')->update(['load_type' => 'dumbbell']);

        DB::table('equipment')->whereIn('title', [
            'Kettlebell', 'Bandas de resistencia', 'TRX', 'Banco',
            'Máquina de remo', 'Cinta de correr', 'Bicicleta estática',
            'Elíptica', 'Peso corporal', 'Cajón pliométrico',
            'Rueda abdominal', 'Fitball',
        ])->update(['load_type' => 'fixed']);

        // Discos de plate/máquina: 1.25/2.5/5/10/15/20kg son todos múltiplos
        // de 1.25 -- redondear a 1.25 garantiza una carga siempre cargable
        // combinando esos discos. No pisa overrides manuales ya existentes.
        DB::table('exercises')
            ->join('equipment', 'exercises.equipment_id', '=', 'equipment.id')
            ->where('equipment.load_type', 'plate')
            ->whereNull('exercises.increment_kg')
            ->update(['exercises.increment_kg' => 1.25]);
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('load_type');
        });
    }
};
