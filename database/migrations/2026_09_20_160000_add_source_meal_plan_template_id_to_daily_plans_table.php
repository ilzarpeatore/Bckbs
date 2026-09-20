<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sin esto no había forma de saber, después de asignar una plantilla
     * (MealPlanTemplateController::importToCalendar()), qué meal_plan_template
     * originó las daily_plan_recipes de un día concreto -- necesario para que
     * la pestaña "Dietas asignadas" del admin (antes leía el sistema viejo
     * Diet/AssignDiet, sin relación con MealPlanTemplate) pueda mostrar qué
     * plantillas tiene asignadas un cliente. Ver Bckbs::docs/... (unificación
     * Diet -> MealPlanTemplate, 2026-09-20).
     */
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('source_meal_plan_template_id')->nullable()->after('user_id')
                ->constrained('meal_plan_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_meal_plan_template_id');
        });
    }
};
