<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Misma trazabilidad que en daily_plan_recipes, para el lado de
     * Entrenamiento: qué Subscription generó este día del calendario
     * del cliente, para poder limpiar solo lo futuro al expirar.
     */
    public function up(): void
    {
        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('source_subscription_id')->nullable()->after('scheduled_date');
            $table->foreign('source_subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('program_day_assignments', function (Blueprint $table) {
            $table->dropForeign(['source_subscription_id']);
            $table->dropColumn('source_subscription_id');
        });
    }
};
