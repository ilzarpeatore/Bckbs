<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_assignments', function (Blueprint $table) {
            // null = asignación recurrente/cuestionario, gobernada por
            // Form::recurrence (comportamiento existente). Con valor = el
            // coach fijó esta asignación a un día concreto del calendario
            // del cliente, independiente de la recurrencia del formulario.
            $table->date('scheduled_date')->nullable()->after('active');
            $table->index('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_assignments', function (Blueprint $table) {
            $table->dropIndex(['scheduled_date']);
            $table->dropColumn('scheduled_date');
        });
    }
};
