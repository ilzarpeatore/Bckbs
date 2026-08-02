<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esto es lo que HubFit llama "Workout" — una rutina de UN SOLO DÍA,
     * reutilizable (la asignas a cualquier día de cualquier mesociclo).
     * NO confundir con la tabla `workouts` existente del proyecto base,
     * que representa el mesociclo entero — esa se mantiene intacta para
     * no romper nada, y esta es la nueva capa que falta.
     */
    public function up(): void
    {
        Schema::create('workout_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title'); // ej. "TRACCIÓN | Mesociclo JULIO"
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_templates');
    }
};
