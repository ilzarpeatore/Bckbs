<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ítem 26 del roadmap: las comidas asignadas desde FatSecret no tienen
     * ingredient_id local (sus ingredientes son texto libre: "6 tazas de
     * brócoli picado"), así que la lista de la compra las omitía en silencio.
     * Ahora se añaden como líneas de texto -- nombre en custom_item_name, la
     * cantidad en display_quantity y la unidad tal cual viene ("taza",
     * "cucharada"...) en esta columna, porque no encaja en measurement_units.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->string('unit_label', 60)->nullable()->after('measurement_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('unit_label');
        });
    }
};
