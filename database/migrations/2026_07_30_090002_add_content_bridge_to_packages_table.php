<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liga un Package (precio/duración/pago, ya existente) a contenido real:
     * un TrainingProgram de librería (is_personal=false) y/o un MealPlanTemplate.
     * Ambos nullable a propósito, para permitir paquetes solo-entrenamiento,
     * solo-nutrición, o combinados (ej. "Definición 3 meses" = programa + dieta).
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedBigInteger('training_program_id')->nullable()->after('id');
            $table->unsignedBigInteger('meal_plan_template_id')->nullable()->after('training_program_id');

            $table->foreign('training_program_id')->references('id')->on('training_programs')->onDelete('set null');
            $table->foreign('meal_plan_template_id')->references('id')->on('meal_plan_templates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['training_program_id']);
            $table->dropForeign(['meal_plan_template_id']);
            $table->dropColumn(['training_program_id', 'meal_plan_template_id']);
        });
    }
};
