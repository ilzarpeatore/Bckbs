<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Auto-Regulación de Carga — cierre automático de mesociclo
     * (achievement_events tipo mesociclo_cerrado, antes sin trigger real).
     *
     * INVESTIGADO ANTES DE CREAR: program_client_assignments ya tiene
     * start_date (equivalente exacto a "fecha_inicio" — la fecha real de
     * asignación de un training_program, reutilizable, a UN cliente
     * concreto), usado ya como fuente de verdad en
     * RealCalendarController::resolveStartDate() por delante de
     * training_programs.fecha_inicio (legacy, solo relevante para el
     * calendario personal 1:1). No se duplica ese campo. Falta el
     * equivalente a "fecha_fin" — no existe hoy en esta tabla.
     *
     * fecha_fin se CALCULA UNA VEZ al asignar/renovar (start_date +
     * num_weeks*7 - 1 día) y se guarda, nunca se recalcula al vuelo — así
     * el job de cierre solo compara `hoy > fecha_fin` sin tener que volver
     * a resolver el training_program en cada pasada.
     *
     * cerrado_at: marca de idempotencia del job diario (check:mesocycle-
     * closures) — null = mesociclo todavía no evaluado/cerrado. Se
     * resetea a null cuando una asignación existente se renueva con un
     * nuevo start_date (ver PlanFulfillmentService/PackageFulfillmentService/
     * TrainingProgramController::assignClient), porque eso es un nuevo
     * ciclo del mesociclo, no una continuación del ya cerrado.
     */
    public function up(): void
    {
        Schema::table('program_client_assignments', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable()->after('start_date');
            $table->timestamp('cerrado_at')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('program_client_assignments', function (Blueprint $table) {
            $table->dropColumn(['fecha_fin', 'cerrado_at']);
        });
    }
};
