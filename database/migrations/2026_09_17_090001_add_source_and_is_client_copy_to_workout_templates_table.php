<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de docs/PLAN_CLONADO_PROGRAMAS.md (§2/§3) — mismo propósito que
 * la migración hermana sobre `training_programs`. Cuando exista el
 * clonado real (Fase 2), cada `WorkoutTemplate` de cliente clonado a
 * partir de una plantilla de biblioteca guardará aquí su origen
 * (`source_workout_template_id`) -- ya existe un patrón idéntico e
 * informativo en `workout_template_blocks.source_section_template_id`,
 * se reutiliza el mismo estilo. `is_client_copy` marcará esas copias
 * exclusivas para excluirlas de la biblioteca reutilizable de plantillas
 * sueltas (Fase 5; `WorkoutTemplateController::getList()` no se toca en
 * esta fase). Aditiva: nullable, default `false`, no muta filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('source_workout_template_id')->nullable()->after('is_demo');
            $table->boolean('is_client_copy')->default(false)->after('source_workout_template_id');

            $table->foreign('source_workout_template_id')->references('id')->on('workout_templates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropForeign(['source_workout_template_id']);
            $table->dropColumn(['source_workout_template_id', 'is_client_copy']);
        });
    }
};
