<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
 * §5) — mismo criterio de idempotencia diaria que readiness_scores
 * (unique client_id+date). `id`/`band` como bigint/string, no uuid/enum de
 * BD — misma convención real ya documentada en coach_exception_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('date');
            $table->unsignedInteger('dias_inactividad')->nullable();
            $table->decimal('compliance_actual', 5, 2)->nullable();
            $table->decimal('compliance_anterior', 5, 2)->nullable();
            $table->unsignedInteger('dias_desde_ultimo_logro')->nullable();
            $table->decimal('dolor_score', 6, 2)->nullable();
            $table->decimal('combined_score', 6, 4)->nullable();
            // App\Enums\RiskBand como string: bajo/medio/alto/dato_insuficiente.
            $table->string('band', 20)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['client_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_risk_scores');
    }
};
