<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los ejercicios "de plantilla" dentro de un section_template — el
     * prescrito (reps/RPE/tempo/nota) y las métricas habilitadas viven
     * aquí, y se copian (no se referencian) al importar la sección
     * dentro de un workout_template concreto — así, editar la plantilla
     * después no afecta a los workouts que ya la usaron (igual que en
     * HubFit: "importar" es una copia, no un enlace en vivo).
     */
    public function up(): void
    {
        Schema::create('section_template_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_template_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedInteger('sequence')->default(0);
            $table->json('prescribed')->nullable();       // reps_min/max, rpe_min/max, tempo, descanso, notas
            $table->json('enabled_metrics')->nullable();   // igual que ya teníamos en workout_day_exercises
            $table->timestamps();

            $table->foreign('section_template_id')->references('id')->on('section_templates')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_template_exercises');
    }
};
