<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favoritos para WorkoutTemplate (sistema v2) - mismo patron que
     * user_favourite_workouts (v1)/user_favourite_recipes, nunca existio
     * para v2 (workout_preview_screen.tsx tenia el boton de guardar solo
     * como estado local, sin persistir nada).
     */
    public function up(): void
    {
        Schema::create('user_favourite_workout_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workout_template_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workout_template_id')->references('id')->on('workout_templates')->onDelete('cascade');
            $table->unique(['user_id', 'workout_template_id'], 'user_fav_workout_templates_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favourite_workout_templates');
    }
};
