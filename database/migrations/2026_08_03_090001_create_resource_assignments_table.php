<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla puente para recursos scope='assigned' - permite asignar el
     * mismo recurso a varios clientes concretos sin duplicar contenido
     * (antes 'resources.client_id' solo soportaba un cliente por fila).
     */
    public function up(): void
    {
        Schema::create('resource_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamp('assigned_at')->useCurrent();

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['resource_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_assignments');
    }
};
