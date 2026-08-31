<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivational_phrases', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            // Placeholders tipo {n} en el texto se sustituyen por el dato real
            // (ej. workouts_this_week=3) al servir la frase. Ver
            // MotivationalPhraseController::getPhrase().
            $table->enum('condition_type', ['workouts_this_week', 'habits_streak', 'general']);
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['condition_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivational_phrases');
    }
};
