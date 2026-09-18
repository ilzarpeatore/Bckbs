<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría 2026-09-18 (bug real de aislamiento de datos): un cliente
 * puede necesitar un bloque entero (ej. "Movilidad extra") que no existe
 * en la plantilla compartida de su sesión. Igual que
 * client_exercise_overrides ya evita tocar workout_template_exercises
 * para ocultar/ajustar un ejercicio, esta tabla evita tocar
 * workout_template_blocks para añadir un bloque -- vive fuera de la
 * plantilla, solo visible para (program_day_assignment_id, client_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_block_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_day_assignment_id');
            $table->unsignedBigInteger('client_id');
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('program_day_assignment_id')->references('id')->on('program_day_assignments')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_block_overrides');
    }
};
