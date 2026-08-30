<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de estado del cliente para flags derivados (Fase 1, §1.3).
     * Por ahora solo pain_pattern_flag: mismo exercise_id + localización con
     * pain_report en 2+ sesiones distintas de los últimos 30 días (detectado
     * por el comando check:pain-patterns).
     */
    public function up(): void
    {
        Schema::create('client_exercise_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            $table->string('localizacion');
            $table->boolean('pain_pattern_flag')->default(false);
            $table->timestamp('flagged_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');

            $table->unique(['client_id', 'exercise_id', 'localizacion'], 'client_exercise_flags_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exercise_flags');
    }
};
