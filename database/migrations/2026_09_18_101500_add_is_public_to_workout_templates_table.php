<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BUG REAL DE PRIVACIDAD (reportado 2026-09-18): la sección
     * "Entrenamientos" del Home de la app (y el catálogo/favoritos de
     * WorkoutTemplateController::getClientList/getClientDetail en general)
     * listaba TODOS los workout_templates sin ningún filtro — incluidos los
     * personalizados para un cliente concreto (asignados a su calendario
     * personal vía ProgramDayAssignment, ver assignDirect() en
     * ClientProfileCalendarController). Cualquier cliente podía ver ahí
     * entrenamientos pensados solo para otro (ej. Nerea veía workouts
     * creados para Borja).
     *
     * default(false), a propósito, aplicado también a TODAS las filas
     * existentes (sin excepción, sin intentar adivinar cuáles "deberían"
     * ser públicas por algún heurístico de uso/asignación): pedido explícito
     * del coach — los entrenamientos nacen privados, y es él quien marca a
     * mano (toggle en el panel Admin, WorkoutTemplatesView.tsx) los que
     * quiere abrir al catálogo público, igual que ya hace con is_exclusive.
     * Cubre también el caso de un borrador de sesión sin terminar que no
     * debería publicarse todavía.
     */
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_exclusive');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
