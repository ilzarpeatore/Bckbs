<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota libre que el cliente escribe por ejercicio durante la sesion
     * (workout_session_screen.tsx) - antes solo vivia en estado local de
     * React, nunca se enviaba al backend ni era visible desde el admin.
     */
    public function up(): void
    {
        Schema::table('client_exercise_logs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('logged_sets');
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_logs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
