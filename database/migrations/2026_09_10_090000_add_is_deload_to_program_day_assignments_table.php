<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 16 ítem
 * 45 (docs/Motor_Autorregulacion_Analisis.md): "conciencia de
 * periodización/deload" en SessionInterpretationService::detectOutliers().
 *
 * No existía NINGÚN concepto de "semana de descarga planificada" en el
 * esquema -- lo hubo (progression_rules, load_multiplier/is_deload en
 * TrainingProgramGeneratorService) pero se retiró explícitamente por no
 * usarse nunca en producción (comentario en ese servicio: "0 filas en 20
 * programas reales"). Se reintroduce aquí, mínimo y opcional (default
 * false, nullable no hace falta), directamente en `program_day_assignments`
 * -- la tabla que Fase 1/Fase 2 YA leen para resolver semana/mesociclo
 * (ver SessionProgressionRuleEngine::resolveFirstWeekPrescribed()) -- en
 * vez de en `workout_days`/`Workout` (la estructura de AUTORÍA del coach,
 * que no es la que el motor consulta en tiempo real y que tiene su propio
 * mecanismo de generación de semanas, sin tocar en esta ronda).
 *
 * El coach marca una semana completa como descarga vía
 * TrainingProgramController::markWeekDeload() (bulk UPDATE de todas las
 * filas de esa semana), no fila a fila -- por eso no hace falta ninguna
 * query de agregación en el motor: la fila de `program_day_assignments`
 * de LA SESIÓN concreta ya trae su propio `is_deload`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->boolean('is_deload')->default(false)->after('week_number');
        });
    }

    public function down(): void
    {
        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->dropColumn('is_deload');
        });
    }
};
