<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sustituye al módulo "Game" original (game_score_data), que era solo
     * una tabla de puntuación sin fechas ni definición de reto. Aquí sí hay
     * fecha de cierre y métrica configurable (ej. "máximo volumen en peso
     * muerto este mes").
     */
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('metric_type'); // ej. volume|weight|steps|habit_streak
            $table->string('target_metric')->nullable(); // ej. "peso_muerto"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('scope')->default('shared'); // shared|personal
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
