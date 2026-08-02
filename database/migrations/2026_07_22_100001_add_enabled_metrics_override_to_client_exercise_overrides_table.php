<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->json('enabled_metrics_override')->nullable()->after('prescribed_override');
        });
    }

    public function down(): void
    {
        Schema::table('client_exercise_overrides', function (Blueprint $table) {
            $table->dropColumn('enabled_metrics_override');
        });
    }
};
