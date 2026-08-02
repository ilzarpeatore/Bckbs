<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag de exclusividad para el gating Free vs Paquete (2026-07-30):
     * un WorkoutTemplate marcado is_exclusive=true solo se ve completo si el
     * usuario es cliente 1:1 o tiene un Package activo con
     * grants_full_workout_library. Un mismo template puede seguir estando
     * dentro de N TrainingProgram de pago (librería compartida) — este flag
     * solo afecta a si aparece "desbloqueado" en la navegación libre de
     * Workouts sueltos.
     */
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->boolean('is_exclusive')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropColumn('is_exclusive');
        });
    }
};
