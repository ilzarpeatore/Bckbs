<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding v2, etapa 3 (cuestionario de entrenamiento) — una fila
     * por usuario, ver docs/ONBOARDING_V2.md. training_experience_months
     * llega ya convertido desde el cliente (rueda de años ×12).
     */
    public function up(): void
    {
        Schema::create('training_questionnaire_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('goal_type', 20);
            $table->string('activity_level', 20);
            $table->string('lifestyle_type', 30);
            $table->unsignedSmallInteger('training_experience_months');
            $table->unsignedTinyInteger('training_days_per_week');
            $table->string('session_duration_preference', 10);
            $table->string('training_mindset', 20);
            $table->string('previous_coaching', 20);
            $table->string('current_routine_style', 20);
            $table->string('weekly_split_preference', 20);
            $table->unsignedTinyInteger('technique_level'); // escala 1-10
            $table->text('realistic_goal');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_questionnaire_answers');
    }
};
