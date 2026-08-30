<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla 'resources' nunca llego a usarse desde la app (0 filas en
     * produccion) - se reemplaza la asignacion 1-a-1 via client_id por la
     * tabla puente resource_assignments (muchos-a-muchos). scope pasa a
     * tener solo 'shared'|'assigned' como valores reales.
     */
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('coach_id');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
