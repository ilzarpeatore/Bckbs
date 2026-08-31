<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Petición del usuario tras ver la feature: poder editar el texto de los 3
 * mensajes de reenganche (dia_7/dia_14/dia_20) desde el panel admin, en vez
 * de solo por código+deploy como estaba planteado originalmente en
 * docs/Score_Riesgo_Abandono_Implementacion.md §8.2. Se guardan por coach
 * (misma tabla y misma fila que los pesos, reutilizada) — nullable: null =
 * usar el texto por defecto de RetentionRiskCalculationService::NUDGE_MESSAGES,
 * mismo criterio de "override opcional" que ya tienen w1-w4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_score_weight_configs', function (Blueprint $table) {
            $table->text('msg_dia_7')->nullable()->after('auto_reengagement_enabled');
            $table->text('msg_dia_14')->nullable()->after('msg_dia_7');
            $table->text('msg_dia_20')->nullable()->after('msg_dia_14');
        });
    }

    public function down(): void
    {
        Schema::table('coach_score_weight_configs', function (Blueprint $table) {
            $table->dropColumn(['msg_dia_7', 'msg_dia_14', 'msg_dia_20']);
        });
    }
};
