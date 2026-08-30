<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Motor de Auto-Regulación de Carga — Fase 2 (documento §2.2). Output
    // real del motor: una fila por evaluación (client_id + exercise_id).
    public function up(): void
    {
        Schema::create('next_session_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            // null si es fallback (aplicar_igual/mantener_sin_cambio sin
            // regla concreta) o calibración/bloqueo por dolor.
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->decimal('proposed_weight', 8, 2)->nullable();
            $table->unsignedInteger('proposed_reps')->nullable();
            $table->string('status', 20)->default('pendiente');
            $table->timestamp('generated_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->foreign('rule_id')->references('id')->on('session_progression_rules')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['client_id', 'exercise_id', 'status'], 'nst_client_exercise_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_session_targets');
    }
};
