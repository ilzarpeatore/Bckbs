<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `workout_days.sequence` ya existe pero es solo un orden plano (1,2,3...),
     * sin noción de semana ni fecha. Se añaden ambos campos sin tocar nada
     * de lo que ya funciona (quedan nullable para no romper filas existentes).
     */
    public function up(): void
    {
        Schema::table('workout_days', function (Blueprint $table) {
            $table->unsignedInteger('week_number')->nullable()->after('sequence');
            $table->date('scheduled_date')->nullable()->after('week_number');
        });
    }

    public function down(): void
    {
        Schema::table('workout_days', function (Blueprint $table) {
            $table->dropColumn(['week_number', 'scheduled_date']);
        });
    }
};
