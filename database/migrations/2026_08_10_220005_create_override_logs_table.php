<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Motor de Auto-Regulación de Carga — Fase 2 (documento §2.3). El
    // documento solo lista created_at (no updated_at) para esta tabla —
    // es un log de auditoría de solo-inserción, nunca se actualiza una
    // fila ya escrita, así que se respeta tal cual (excepción explícita a
    // la regla general de created_at/updated_at, el propio esquema de la
    // tabla lo indica al omitir updated_at).
    public function up(): void
    {
        Schema::create('override_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('next_session_target_id');
            // Puede ser null: un next_session_target de fallback o de
            // calibración/bloqueo por dolor no tiene rule_id.
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
            $table->decimal('suggested_value', 8, 2);
            $table->decimal('applied_value', 8, 2);
            $table->string('action_taken', 20);
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('next_session_target_id')->references('id')->on('next_session_targets')->onDelete('cascade');
            $table->foreign('rule_id')->references('id')->on('session_progression_rules')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->index(['rule_id', 'client_id'], 'ol_rule_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_logs');
    }
};
