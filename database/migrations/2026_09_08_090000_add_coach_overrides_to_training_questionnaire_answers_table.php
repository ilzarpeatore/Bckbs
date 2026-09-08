<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 7 ítems
 * 23-24 (docs/Motor_Autorregulacion_Analisis.md): el dato de
 * `training_experience_months`/`technique_level` ya existe (autoevaluado
 * por el cliente en el onboarding), pero solo el propio cliente puede
 * escribirlo — un coach que evalúa presencialmente a alguien más avanzado
 * o menos de lo que se autoevaluó no tiene forma de corregirlo.
 *
 * Columnas de override separadas (no se pisa el valor autoevaluado) para
 * conservar trazabilidad de qué dijo el cliente vs. qué confirmó/corrigió
 * el coach — el motor de reglas (Ronda 7 ítem 25) prioriza el override
 * cuando existe, ver TrainingQuestionnaireAnswer::effectiveExperienceMonths().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            $table->unsignedSmallInteger('training_experience_months_coach')->nullable()->after('training_experience_months');
            $table->unsignedTinyInteger('technique_level_coach')->nullable()->after('technique_level');
            $table->unsignedBigInteger('overridden_by_id')->nullable()->after('realistic_goal');
            $table->timestamp('overridden_at')->nullable()->after('overridden_by_id');

            $table->foreign('overridden_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            $table->dropForeign(['overridden_by_id']);
            $table->dropColumn(['training_experience_months_coach', 'technique_level_coach', 'overridden_by_id', 'overridden_at']);
        });
    }
};
