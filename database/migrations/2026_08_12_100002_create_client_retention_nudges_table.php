<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
 * §8.3). `episode_reference_date` (fecha de la última sesión completada
 * antes de empezar el episodio de inactividad actual) identifica el
 * episodio -- el unique de abajo resuelve el reset de etapas sin lógica
 * adicional (§8.4): si el cliente vuelve a entrenar y cae en inactividad
 * de nuevo, esa fecha cambia y las 3 etapas quedan libres otra vez solas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_retention_nudges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            // App\Enums\RetentionNudgeStage: dia_7/dia_14/dia_20.
            $table->string('stage', 20);
            $table->date('episode_reference_date');
            $table->timestamp('sent_at')->nullable();
            // Único canal hoy ('push'), columna abierta a email/sms futuros.
            $table->string('channel', 20)->default('push');
            $table->string('status', 20); // enviado | fallido
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            // Nombre explícito -- el autogenerado por Laravel supera el
            // límite de 64 caracteres de MySQL para identificadores.
            $table->unique(['client_id', 'stage', 'episode_reference_date'], 'nudges_client_stage_episode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_retention_nudges');
    }
};
