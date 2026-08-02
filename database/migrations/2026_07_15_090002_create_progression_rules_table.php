<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantilla de progresión CONFIGURABLE por programa — nada hardcodeado.
     * El coach define, semana a semana, el multiplicador de carga y si es deload
     * o si se permiten técnicas especiales esa semana. El motor solo ejecuta
     * lo que aquí se defina, no asume ningún valor por defecto propio del código.
     */
    public function up(): void
    {
        Schema::create('progression_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_program_id');
            $table->unsignedInteger('week_number');
            $table->decimal('load_multiplier', 5, 2)->default(1.00);
            $table->boolean('is_deload')->default(false);
            $table->boolean('allow_special_techniques')->default(false);
            $table->timestamps();

            $table->foreign('training_program_id')->references('id')->on('training_programs')->onDelete('cascade');
            $table->unique(['training_program_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_rules');
    }
};
