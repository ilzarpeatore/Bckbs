<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El diseño del Asistente de Programación de Entrenamiento (repo
 * AgenticdesignBS, agentes/programacion-entrenamiento/modulos/
 * contraindicaciones-medicas.md) asume tres preguntas de cribado de
 * seguridad -- embarazo/posibilidad, alteración menstrual o fractura por
 * estrés (cribado básico de RED-S), y trastorno de conducta alimentaria --
 * que par_q_answers nunca llegó a recoger. Sin este dato, ese módulo no
 * tiene nada que leer: el bloqueo que describe en prosa no puede activarse
 * en la app real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('par_q_answers', function (Blueprint $table) {
            $table->boolean('parq_pregnant_or_possible')->nullable()->after('parq_reason_not_to_exercise');
            $table->boolean('parq_menstrual_change_or_stress_fracture')->nullable()->after('parq_pregnant_or_possible');
            $table->boolean('parq_eating_disorder_history')->nullable()->after('parq_menstrual_change_or_stress_fracture');
        });
    }

    public function down(): void
    {
        Schema::table('par_q_answers', function (Blueprint $table) {
            $table->dropColumn([
                'parq_pregnant_or_possible',
                'parq_menstrual_change_or_stress_fracture',
                'parq_eating_disorder_history',
            ]);
        });
    }
};
