<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca un Form (questionnaire/check-in) como "estándar": se asigna
     * automáticamente a TODO cliente nuevo en el registro (UserController::register),
     * en vez de requerir que un coach lo asigne manualmente uno por uno
     * desde el panel Admin (admin-form-assign).
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('auto_assign_all_clients')->default(false)->after('recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('auto_assign_all_clients');
        });
    }
};
