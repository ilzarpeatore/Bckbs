<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable a propósito: los workout_day_exercises que ya existen
     * (creados antes de este cambio) siguen funcionando sin bloque
     * asignado — se tratan como "sin agrupar" hasta que el coach los
     * organice en bloques.
     */
    public function up(): void
    {
        Schema::table('workout_day_exercises', function (Blueprint $table) {
            $table->unsignedBigInteger('workout_day_block_id')->nullable()->after('workout_day_id');
            $table->foreign('workout_day_block_id')->references('id')->on('workout_day_blocks')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('workout_day_exercises', function (Blueprint $table) {
            $table->dropForeign(['workout_day_block_id']);
            $table->dropColumn('workout_day_block_id');
        });
    }
};
