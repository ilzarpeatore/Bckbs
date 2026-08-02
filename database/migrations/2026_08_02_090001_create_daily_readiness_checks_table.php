<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulario diario obligatorio (salvo que el admin lo desactive para
     * un cliente concreto via ClientFeatureSetting 'readiness_check') que
     * se rellena antes de Workout Preview. Un registro por usuario/dia.
     */
    public function up(): void
    {
        Schema::create('daily_readiness_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->unsignedTinyInteger('sleep_quality'); // 1-5
            $table->unsignedTinyInteger('soreness_level'); // 1-10
            $table->unsignedTinyInteger('energy_level'); // 1-5
            $table->unsignedTinyInteger('stress_level'); // 1-5
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_readiness_checks');
    }
};
