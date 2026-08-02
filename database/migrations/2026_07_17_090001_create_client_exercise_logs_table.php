<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equivalente a `user_exercises.logged_sets` del sistema viejo, pero
     * para el sistema nuevo (workout_templates). Cada fila = una sesión
     * (INSERT siempre, nunca update, mismo criterio que ya corregimos
     * antes — para conservar historial real de progreso).
     */
    public function up(): void
    {
        Schema::create('client_exercise_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('workout_template_exercise_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedBigInteger('program_day_assignment_id')->nullable();
            $table->date('performed_date')->nullable();
            $table->json('logged_sets')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workout_template_exercise_id')->references('id')->on('workout_template_exercises')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->foreign('program_day_assignment_id')->references('id')->on('program_day_assignments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exercise_logs');
    }
};
