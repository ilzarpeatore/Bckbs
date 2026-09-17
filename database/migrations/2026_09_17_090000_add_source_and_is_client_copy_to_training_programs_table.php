<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de docs/PLAN_CLONADO_PROGRAMAS.md (§2/§3) — columnas de linaje
 * aditivas, sin ningún cambio de comportamiento todavía. Hoy
 * `program_client_assignments.training_program_id` apunta siempre a la
 * plantilla de biblioteca compartida (nunca se clona), lo cual es el bug
 * raíz que este plan corrige en fases posteriores. `source_training_program_id`
 * guardará, una vez exista el clonado real (Fase 2), de qué plantilla de
 * biblioteca viene la copia exclusiva de un cliente -- necesario para que
 * el motor de Auto-Regulación de Carga (`SessionProgressionRuleEngine`,
 * Riesgo A del plan) siga resolviendo reglas `programa_especifico` contra
 * el origen y no contra el id del clon. `is_client_copy` marcará esas
 * copias para excluirlas de listados/biblioteca (Fase 5). Nullable y con
 * default `false`: no toca ninguna fila existente ni cambia ninguna query
 * actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->unsignedBigInteger('source_training_program_id')->nullable()->after('source_id');
            $table->boolean('is_client_copy')->default(false)->after('source_training_program_id');

            $table->foreign('source_training_program_id')->references('id')->on('training_programs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropForeign(['source_training_program_id']);
            $table->dropColumn(['source_training_program_id', 'is_client_copy']);
        });
    }
};
