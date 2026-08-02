<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entitlements de solo-lectura (no generan filas de calendario, a
     * diferencia de training_program_id/meal_plan_template_id): un Package
     * puede además regalar acceso completo a la librería de Workouts y/o
     * Recetas exclusivos, sin estar ligado a un programa/plantilla concreto
     * (ej. paquete "Full Access Workouts"), o combinado con un Programa de
     * pago (ej. el programa de $50 que además regala ambas librerías).
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('grants_full_workout_library')->default(false)->after('meal_plan_template_id');
            $table->boolean('grants_full_recipe_library')->default(false)->after('grants_full_workout_library');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['grants_full_workout_library', 'grants_full_recipe_library']);
        });
    }
};
