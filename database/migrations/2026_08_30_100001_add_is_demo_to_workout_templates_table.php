<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AÑADIDO: marca el WorkoutTemplate de bienvenida que siembra
     * DemoWorkoutTemplateSeeder, para poder localizarlo de forma estable
     * desde UserController::register() (auto-asignación en el alta de un
     * cliente nuevo) sin depender de su título, que un coach podría
     * renombrar más adelante desde el panel Admin.
     */
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_exclusive');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
