<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 8
 * (docs/Motor_Autorregulacion_Analisis.md): tonelaje real (peso × reps de
 * cada set completado, sumado) y su tendencia -- hasta ahora
 * `carga_efectiva` (el pico de UN set) era la única señal de "carga" de
 * Fase 1, sin ningún dato de trabajo total de la sesión. Columnas
 * nullable/aditivas, no tocan ninguna lectura existente de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->decimal('volumen_total', 10, 2)->nullable()->after('carga_efectiva');
            $table->decimal('tendencia_volumen', 10, 3)->nullable()->after('tendencia_rir');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_session_metrics', function (Blueprint $table) {
            $table->dropColumn(['volumen_total', 'tendencia_volumen']);
        });
    }
};
