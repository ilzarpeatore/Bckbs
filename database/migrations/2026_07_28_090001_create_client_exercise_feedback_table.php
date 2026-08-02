<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencia explícita del cliente sobre un ejercicio (like/dislike),
     * usada para sesgar futuras recomendaciones de sustitución. Solo
     * persiste el dato — no hay motor de recomendación todavía.
     */
    public function up(): void
    {
        Schema::create('client_exercise_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            $table->string('feedback'); // 'like' | 'dislike'
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->unique(['client_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exercise_feedback');
    }
};
