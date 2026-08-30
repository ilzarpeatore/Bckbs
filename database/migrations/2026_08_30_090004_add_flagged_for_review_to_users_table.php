<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding v2, etapa 2 (PAR-Q) — si el usuario contesta "Sí" a alguna
     * pregunta de riesgo cardíaco/mareos, se marca el perfil para que un
     * coach lo revise antes de asignar un plan (decisión de producto
     * confirmada, ver docs/ONBOARDING_V2.md).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('flagged_for_review')->default(false)->nullable()->after('coach_id');
            $table->timestamp('flagged_for_review_at')->nullable()->after('flagged_for_review');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['flagged_for_review', 'flagged_for_review_at']);
        });
    }
};
