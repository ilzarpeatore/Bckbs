<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding v2, etapa 4 (cuestionario de nutrición) — una fila por
     * usuario, ver docs/ONBOARDING_V2.md.
     */
    public function up(): void
    {
        Schema::create('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('allergies_intolerances');
            $table->text('disliked_foods')->nullable();
            $table->text('liked_foods')->nullable();
            $table->unsignedTinyInteger('current_meals_per_day');
            $table->unsignedTinyInteger('desired_meals_per_day');
            $table->text('typical_day_meals');
            $table->text('favorite_meats')->nullable();
            $table->text('favorite_fish')->nullable();
            $table->text('favorite_fruits_vegetables')->nullable();
            $table->text('favorite_combined_dishes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_questionnaire_answers');
    }
};
