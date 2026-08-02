<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORRECCIÓN: program_day_assignments es contenido de PLANTILLA
     * compartido (una fila por semana/día de un training_program de
     * librería, reutilizada por todos los clientes asignados a él) —
     * no tiene dueño de una compra concreta, así que source_subscription_id
     * no pertenece ahí. El puntero real cliente↔programa es
     * program_client_assignments (training_program_id + client_id +
     * start_date) — ahí sí pertenece, uno por cliente por programa.
     */
    public function up(): void
    {
        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->dropForeign(['source_subscription_id']);
            $table->dropColumn('source_subscription_id');
        });

        Schema::table('program_client_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('source_subscription_id')->nullable()->after('start_date');
            $table->foreign('source_subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('program_client_assignments', function (Blueprint $table) {
            $table->dropForeign(['source_subscription_id']);
            $table->dropColumn('source_subscription_id');
        });

        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('source_subscription_id')->nullable()->after('scheduled_date');
            $table->foreign('source_subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }
};
