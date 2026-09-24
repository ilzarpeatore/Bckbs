<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entrenamientos personalizados creados por el propio cliente desde la
     * app (ClientCustomWorkoutController). Se guardan como un
     * workout_template normal + program_day_assignment en su calendario
     * personal (mismo mecanismo que assignDirect() del panel), así que la
     * sesión, el registro de series, el feedback y el historial funcionan
     * sin cambios. Estas dos columnas solo sirven para:
     *
     * - created_by_client_id: distinguir "lo creó el cliente" de "lo asignó
     *   el coach" (badge en calendario/panel, y permiso para que el cliente
     *   lo borre -- nunca puede borrar lo que asignó su coach).
     * - client_series_uuid: agrupar las repeticiones semanales de una misma
     *   creación ("repetir todos los lunes"), para poder borrar "esta y las
     *   siguientes" de una vez. Cada ocurrencia tiene su propia copia de la
     *   plantilla (mismo criterio que cloneStructure(): ninguna
     *   asignación comparte fila con otra).
     */
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_client_id')->nullable()->after('coach_id');
            $table->string('client_series_uuid', 36)->nullable()->after('created_by_client_id');
            $table->foreign('created_by_client_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('client_series_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropForeign(['created_by_client_id']);
            $table->dropIndex(['client_series_uuid']);
            $table->dropColumn(['created_by_client_id', 'client_series_uuid']);
        });
    }
};
