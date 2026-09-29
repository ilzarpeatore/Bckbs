<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding optimizado (2026-09-29): a quien nunca ha entrenado
 * (training_experience_months = 0) ya no se le pregunta cómo entrenaba antes,
 * su técnica, su mentalidad, si tuvo entrenador, cómo son sus rutinas ni qué
 * reparto semanal prefiere -- no aplica. Las columnas pasan a admitir NULL;
 * la validación las sigue exigiendo si hay experiencia (ver
 * OnboardingAnswersService::trainingRules).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'training_mindset' => ['string', 20],
        'previous_coaching' => ['string', 20],
        'current_routine_style' => ['string', 20],
        'weekly_split_preference' => ['string', 20],
    ];

    public function up(): void
    {
        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => [$type, $length]) {
                $table->string($column, $length)->nullable()->change();
            }
            $table->unsignedTinyInteger('technique_level')->nullable()->change();
            $table->text('realistic_goal')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => [$type, $length]) {
                $table->string($column, $length)->nullable(false)->change();
            }
            $table->unsignedTinyInteger('technique_level')->nullable(false)->change();
            $table->text('realistic_goal')->nullable(false)->change();
        });
    }
};
