<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORREGIDO: el nombre automático del índice único era demasiado
     * largo para MySQL (límite de 64 caracteres) — se le da un nombre
     * corto a mano ('pda_program_week_day_unique').
     */
    public function up(): void
    {
        Schema::create('program_day_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_program_id');
            $table->unsignedInteger('week_number');
            $table->unsignedInteger('day_of_week'); // 1=Lunes ... 7=Domingo
            $table->unsignedBigInteger('workout_template_id')->nullable(); // null = día de descanso
            $table->date('scheduled_date')->nullable();
            $table->timestamps();

            $table->foreign('training_program_id')->references('id')->on('training_programs')->onDelete('cascade');
            $table->foreign('workout_template_id')->references('id')->on('workout_templates')->onDelete('set null');
            $table->unique(['training_program_id', 'week_number', 'day_of_week'], 'pda_program_week_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_day_assignments');
    }
};
