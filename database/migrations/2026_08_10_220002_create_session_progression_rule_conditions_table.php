<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
    // Una regla puede tener varias condiciones combinadas: AND dentro del
    // mismo logic_group, OR entre grupos distintos (documento §2.2 paso 5).
    public function up(): void
    {
        Schema::create('session_progression_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->string('variable', 60);
            $table->string('operator', 30);
            $table->decimal('threshold_value', 8, 3)->nullable();
            $table->decimal('threshold_min', 8, 3)->nullable();
            $table->decimal('threshold_max', 8, 3)->nullable();
            $table->unsignedInteger('ventana_sesiones')->default(1);
            $table->unsignedInteger('logic_group')->default(0);
            $table->timestamps();

            $table->foreign('rule_id')->references('id')->on('session_progression_rules')->onDelete('cascade');
            $table->index(['rule_id', 'logic_group'], 'sprc_rule_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_progression_rule_conditions');
    }
};
