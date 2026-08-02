<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques manuales dentro de un día de entrenamiento — igual que en
     * HubFit: el coach crea el bloque con el título que quiera
     * ("Calentamiento", "Parte principal", "Cardio"...) y luego asigna
     * ejercicios a ese bloque. NO es un enum fijo de tipos de bloque,
     * es texto libre + orden, coherente con "nada hardcodeado".
     */
    public function up(): void
    {
        Schema::create('workout_day_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_day_id');
            $table->string('title'); // texto libre, ej. "Calentamiento"
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('workout_day_id')->references('id')->on('workout_days')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_day_blocks');
    }
};
