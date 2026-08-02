<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques dentro de un workout_template. `source_section_template_id`
     * es opcional y solo informativo (para saber "esto vino de la sección
     * X"), pero NO es una referencia viva — al importar, los ejercicios
     * se copian, así que editar el section_template original después no
     * cambia nada aquí (mismo comportamiento que "importar" en HubFit).
     */
    public function up(): void
    {
        Schema::create('workout_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_template_id');
            $table->unsignedBigInteger('source_section_template_id')->nullable();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('workout_template_id')->references('id')->on('workout_templates')->onDelete('cascade');
            $table->foreign('source_section_template_id')->references('id')->on('section_templates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_template_blocks');
    }
};
