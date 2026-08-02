<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Catálogo de métricas disponibles para rellenar por serie
     * (series, reps, carga, tiempo, tempo, descanso, RIR, RPE...).
     * NO es un enum fijo en código: es una tabla que el coach puede
     * ampliar desde el panel Admin si mañana necesita una métrica nueva
     * (ej. "cadencia", "potencia") sin tocar ni una línea de código.
     */
    public function up(): void
    {
        Schema::create('metrics_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // ej. 'series', 'reps', 'carga', 'rpe'
            $table->string('label');         // ej. 'Series', 'Repeticiones', 'Carga (kg)'
            $table->string('unit')->nullable(); // ej. 'kg', 'seg', null
            $table->string('input_type')->default('number'); // number|text|time
            $table->boolean('higher_is_better')->nullable(); // true=carga/volumen, false=RIR, null=no aplica (ej. tempo)
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Datos de partida — editables/ampliables después, no una lista cerrada.
        DB::table('metrics_catalog')->insert([
            ['key' => 'series',    'label' => 'Series',              'unit' => null,   'input_type' => 'number', 'higher_is_better' => null,  'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'reps',      'label' => 'Repeticiones',        'unit' => null,   'input_type' => 'number', 'higher_is_better' => true,  'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'carga',     'label' => 'Carga',               'unit' => 'kg',   'input_type' => 'number', 'higher_is_better' => true,  'order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tiempo',    'label' => 'Tiempo',              'unit' => 'seg',  'input_type' => 'number', 'higher_is_better' => null,  'order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tempo',     'label' => 'Tempo',               'unit' => null,   'input_type' => 'text',   'higher_is_better' => null,  'order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'descanso',  'label' => 'Descanso',            'unit' => 'seg',  'input_type' => 'number', 'higher_is_better' => null,  'order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'rir',       'label' => 'RIR',                 'unit' => null,   'input_type' => 'number', 'higher_is_better' => false, 'order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'rpe',       'label' => 'RPE',                 'unit' => null,   'input_type' => 'number', 'higher_is_better' => true,  'order' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_catalog');
    }
};
