<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.1).
     * Mapeo de variantes por categoría, definido por el coach. Al ejecutar
     * la acción sustituir_ejercicio, SessionProgressionRuleEngine busca
     * aquí una variante para original_exercise_id (dentro de las reglas de
     * ESE coach); si no existe, degrada a marcar_para_coach — mismo
     * comportamiento de fallback ya implementado en Fase 2, ahora
     * condicionado a que esta tabla esté realmente vacía para ese
     * ejercicio (antes degradaba siempre, sin excepción).
     */
    public function up(): void
    {
        Schema::create('exercise_substitutions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('original_exercise_id');
            $table->unsignedBigInteger('substitute_exercise_id');
            $table->string('category')->nullable();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('original_exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->foreign('substitute_exercise_id')->references('id')->on('exercises')->onDelete('cascade');

            $table->index(['coach_id', 'original_exercise_id'], 'exercise_substitutions_coach_original_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_substitutions');
    }
};
