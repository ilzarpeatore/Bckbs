<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procedencia del programa para los imports (hevy/strong/jefit/wger...):
     * `source` es el nombre de la fuente y `source_id` el id/ref único en esa
     * fuente. Sirven para re-imports idempotentes (no duplicar) y para poder
     * filtrar "programas importados" en el panel.
     */
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_free_accessible');
            $table->string('source_id')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_id']);
        });
    }
};
