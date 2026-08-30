<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding v2, etapa 2 (PAR-Q+) — una fila por usuario, ver
     * docs/ONBOARDING_V2.md. Numeración de preguntas conservada tal cual
     * (continúa un PAR-Q+ estándar cuyas preguntas 1-2 no se piden aquí).
     */
    public function up(): void
    {
        Schema::create('par_q_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('parq_heart_condition');
            $table->boolean('parq_chest_pain_activity');
            $table->boolean('parq_chest_pain_rest_last_month');
            $table->boolean('parq_dizziness_balance');
            $table->boolean('parq_bone_joint_problem');
            $table->boolean('parq_bp_or_heart_medication');
            $table->boolean('parq_reason_not_to_exercise');
            $table->unsignedTinyInteger('parq_fitness_level'); // escala 1-10
            $table->text('parq_medical_history')->nullable();
            $table->text('parq_goals');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('par_q_answers');
    }
};
