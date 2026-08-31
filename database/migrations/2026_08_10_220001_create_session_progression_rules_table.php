<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
     *
     * NOTA DE NOMBRADO: el documento original llama a esta tabla
     * "progression_rules", pero esa tabla YA EXISTE en el esquema real
     * (2026_07_15_090002_create_progression_rules_table.php) con un
     * propósito totalmente distinto: plantilla de progresión POR PROGRAMA
     * (semana -> load_multiplier/is_deload), usada por
     * TrainingProgramGeneratorService para generar programas, sin relación
     * alguna con el motor de auto-regulación por sesión que exige este
     * documento (condiciones sobre exercise_session_metrics, scope por
     * cliente/ejercicio/categoría, etc.). Reutilizar el nombre habría
     * chocado con datos de producción reales de un feature ya en uso. Se
     * prefija "session_" para dejar la distinción explícita: esta regla
     * actúa sesión a sesión sobre el auto-regulador de carga, la otra
     * actúa semana a semana sobre la generación de programas.
     */
    public function up(): void
    {
        Schema::create('session_progression_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('name');
            // scope_type/mode/fallback_behavior como Enums nativos de PHP 8.1+
            // (App\Enums\*), almacenados como string en columna.
            $table->string('scope_type', 40);
            // id de categoría (BodyPart)/ejercicio/cliente según scope_type,
            // null cuando scope_type = global.
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->string('mode', 40);
            $table->string('fallback_behavior', 60);
            $table->boolean('shadow_mode')->default(false);
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['scope_type', 'scope_id', 'active'], 'spr_scope_active_idx');
            $table->index(['coach_id', 'active'], 'spr_coach_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_progression_rules');
    }
};
