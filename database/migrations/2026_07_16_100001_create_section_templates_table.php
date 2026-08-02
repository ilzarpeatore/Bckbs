<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de bloques reutilizables (equivalente a "Sections" en
     * HubFit). Se crean UNA vez ("Activación", "Parte Principal Cuádriceps
     * empuje B"...) y se clonan dentro de cualquier workout_template sin
     * tener que recrear los ejercicios cada vez.
     */
    public function up(): void
    {
        Schema::create('section_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title'); // texto libre, ej. "Activación"
            $table->text('instructions')->nullable(); // "Add instructions for this section"
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_templates');
    }
};
