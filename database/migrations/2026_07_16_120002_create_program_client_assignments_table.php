<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pieza que faltaba: un training_program (plantilla reutilizable)
     * se puede asignar a VARIOS clientes, cada uno con su propia fecha
     * de inicio y estado activo/inactivo — sin duplicar la plantilla.
     * Mismo patrón que "asignar Workout a un cliente" en HubFit.
     */
    public function up(): void
    {
        Schema::create('program_client_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_program_id');
            $table->unsignedBigInteger('client_id');
            $table->date('start_date');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('training_program_id')->references('id')->on('training_programs')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_client_assignments');
    }
};
