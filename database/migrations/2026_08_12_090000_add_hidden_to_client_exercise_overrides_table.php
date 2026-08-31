<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Auto-Regulación de Carga — cierre de "modo vida real"
 * (aprobado -> aplicado). `program_day_assignments` es una plantilla
 * compartida entre todos los clientes de un training_program — no se puede
 * marcar "saltar este día" ahí sin afectar a otros clientes. Se reutiliza
 * `client_exercise_overrides` (ya es per-cliente) en vez de crear una tabla
 * nueva de "día saltado": ocultar TODOS los ejercicios de un día para un
 * cliente concreto logra el mismo efecto que "saltar el día", con el mismo
 * mecanismo que ya usa `accessory_trims` (recorte parcial de accesorios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('prescribed_override');
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->dropColumn('hidden');
        });
    }
};
