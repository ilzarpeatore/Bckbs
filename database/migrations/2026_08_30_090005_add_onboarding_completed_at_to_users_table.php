<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding v2 — flag server-side de onboarding completado, para que
     * un usuario que ya onboardeó no vuelva a ver el flujo entero al
     * reinstalar la app o cambiar de dispositivo (antes solo vivía en
     * AsyncStorage, ver docs/ONBOARDING_V2.md).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('flagged_for_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
