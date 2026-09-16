<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `disponibilidad_cocina` en perfil-nutricional.schema.json (repo
 * AgenticdesignBS) es un campo requerido por el Asistente de Programación
 * de Nutrición desde su primer borrador, pero nunca se llegó a preguntar en
 * el onboarding real -- sin esto, el Productor no sabe si puede proponer
 * recetas de 45 minutos o solo de 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->unsignedSmallInteger('cooking_minutes_per_meal')->nullable()->after('desired_meals_per_day');
            $table->string('cooking_skill_level', 20)->nullable()->after('cooking_minutes_per_meal'); // beginner|intermediate|advanced
            $table->boolean('cooks_for_others')->nullable()->after('cooking_skill_level');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->dropColumn(['cooking_minutes_per_meal', 'cooking_skill_level', 'cooks_for_others']);
        });
    }
};
