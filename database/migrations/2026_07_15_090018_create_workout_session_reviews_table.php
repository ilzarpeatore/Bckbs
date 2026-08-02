<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pantalla "Review Workout" vista en HubFit: valoración de dificultad
     * (escala de emojis Okay->Amazing) + comentario libre, al cerrar sesión.
     */
    public function up(): void
    {
        Schema::create('workout_session_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workout_day_id');
            $table->unsignedTinyInteger('difficulty_rating')->nullable(); // 1=Okay .. 5=Amazing
            $table->text('comment')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_session_reviews');
    }
};
