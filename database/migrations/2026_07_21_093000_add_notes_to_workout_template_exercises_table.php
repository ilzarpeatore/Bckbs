<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('enabled_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
