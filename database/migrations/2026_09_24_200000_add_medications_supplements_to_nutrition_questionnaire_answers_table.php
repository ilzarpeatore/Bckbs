<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medicamentos y suplementos pasan a preguntarse en el onboarding v2 (junto a
 * alergias/intolerancias) en vez de en el cuestionario aparte "Perfil y Salud
 * Inicial", que se deja de asignar. Ambos opcionales: las versiones de la app
 * que aún no los envían siguen funcionando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->text('medications')->nullable()->after('allergies_intolerances');
            $table->text('supplements')->nullable()->after('medications');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->dropColumn(['medications', 'supplements']);
        });
    }
};
