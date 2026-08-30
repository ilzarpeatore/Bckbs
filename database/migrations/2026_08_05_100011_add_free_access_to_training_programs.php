<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->boolean('is_free_accessible')->default(false);
            $table->unsignedBigInteger('billing_plan_id')->nullable();
            $table->foreign('billing_plan_id')->references('id')->on('plans')->nullOnDelete();
        });

        Schema::table('workout_templates', function (Blueprint $table) {
            if (Schema::hasColumn('workout_templates', 'status')) {
                $table->boolean('is_free_accessible')->default(false)->after('status');
            } else {
                $table->boolean('is_free_accessible')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropForeign(['billing_plan_id']);
            $table->dropColumn(['is_free_accessible', 'billing_plan_id']);
        });

        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropColumn('is_free_accessible');
        });
    }
};
