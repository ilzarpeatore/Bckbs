<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1, §2.2 paso 7).
    // 1 acción por regla (documento: "ejecutar la acción de la regla
    // ganadora", singular) — unique(rule_id) lo hace explícito en el
    // esquema en vez de dejarlo como convención implícita.
    public function up(): void
    {
        Schema::create('session_progression_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->string('type', 40);
            $table->decimal('value', 8, 3)->nullable();
            $table->string('rounding', 20);
            $table->string('base_reference', 40);
            $table->timestamps();

            $table->foreign('rule_id')->references('id')->on('session_progression_rules')->onDelete('cascade');
            $table->unique('rule_id', 'spra_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_progression_rule_actions');
    }
};
