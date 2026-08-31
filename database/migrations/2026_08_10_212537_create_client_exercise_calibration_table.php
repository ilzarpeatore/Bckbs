<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cold start (documento §2.4). Genuinamente nueva, se usa de pleno en
     * Fase 2 (ProgressionRuleEngine), pero el incremento de
     * sessions_completed ya se hace en Fase 1 dentro de finishSession(),
     * para que Fase 2 solo tenga que leerla.
     */
    public function up(): void
    {
        Schema::create('client_exercise_calibration', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedInteger('sessions_completed')->default(0);
            $table->boolean('calibration_complete')->default(false);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');

            $table->unique(['client_id', 'exercise_id'], 'client_exercise_calibration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exercise_calibration');
    }
};
