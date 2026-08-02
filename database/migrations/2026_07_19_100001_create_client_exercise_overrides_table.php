<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un mismo workout_template puede estar asignado a varios clientes a
     * la vez (es una plantilla reutilizable). Esta tabla permite que CADA
     * cliente tenga su propio prescrito (sets/reps/carga/rpe) y sus
     * propias notas para una sesión concreta, SIN tocar la plantilla
     * compartida — así el progreso de un cliente no afecta al de otro.
     *
     * Se identifica por (program_day_assignment_id + client_id +
     * workout_template_exercise_id) — necesarios los tres, porque el
     * mismo program_day_assignment_id puede pertenecer a un programa
     * compartido por varios clientes distintos.
     */
    public function up(): void
    {
        Schema::create('client_exercise_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_day_assignment_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('workout_template_exercise_id');
            $table->json('prescribed_override')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('program_day_assignment_id')->references('id')->on('program_day_assignments')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workout_template_exercise_id')->references('id')->on('workout_template_exercises')->onDelete('cascade');

            $table->unique(['program_day_assignment_id', 'client_id', 'workout_template_exercise_id'], 'client_exercise_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exercise_overrides');
    }
};
