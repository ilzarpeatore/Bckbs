<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->after('recipe_id')
                ->comment('coach/admin who assigned this meal, null if the client added it themselves');
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_user_id']);
            $table->dropColumn('assigned_by_user_id');
        });
    }
};
