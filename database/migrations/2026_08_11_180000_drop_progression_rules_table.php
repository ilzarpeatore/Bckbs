<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el modelador de semanas de descarga (load_multiplier/is_deload
 * por training_program+week_number) — nunca se usó en producción (0 filas
 * en 20 programas reales, verificado antes de decidir el borrado). La
 * progresión de carga real la decide el Motor de Auto-Regulación
 * (session_progression_rules) sesión a sesión, no un multiplicador fijado
 * al crear el programa. Decisión explícita del usuario, 2026-08-11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('progression_rules');
    }

    public function down(): void
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
};
