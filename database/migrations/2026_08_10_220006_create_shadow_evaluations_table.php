<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Motor de Auto-Regulación de Carga — Fase 2 (documento §2.6, modo
    // sombra). "Mismo esquema que next_session_targets" — se replica tal
    // cual, sin FKs cruzadas entre ambas tablas (son universos paralelos).
    public function up(): void
    {
        Schema::create('shadow_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_id');
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
            $table->index(['rule_id', 'client_id', 'exercise_id'], 'se_rule_client_exercise_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shadow_evaluations');
    }
};
