<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExerciseTypeToExercisesTable extends Migration
{
    /**
     * Distinta de la columna `type` ya existente (esa es 'sets'/'duration',
     * cómo se registra el ejercicio) — esta es la categoría de
     * entrenamiento (Fuerza/Movilidad/Pliometría/Metabólico/Cardio) que
     * pide el filtro nuevo.
     */
    public function up()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type', 30)->nullable()->after('type');
        });
    }

    public function down()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('exercise_type');
        });
    }
}
