<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macrociclos (página /macrociclos del panel, 2026-09-27): hasta ahora la
 * pertenencia de un mesociclo a un macrociclo solo se deducía del título
 * (App\Support\MacrocycleTitle). Estas dos columnas, opcionales, permiten
 * al coach asignarlo a mano desde el panel; si macrocycle_name está
 * relleno manda sobre lo que diga el título. Null = se sigue deduciendo
 * del título, como antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->string('macrocycle_name', 150)->nullable()->after('title');
            $table->unsignedSmallInteger('mesocycle_number')->nullable()->after('macrocycle_name');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn(['macrocycle_name', 'mesocycle_number']);
        });
    }
};
