<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft deletes
        Schema::table('workout_templates', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('training_programs', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('exercises', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('program_day_assignments', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('workout_template_blocks', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('workout_template_exercises', fn (Blueprint $t) => $t->softDeletes());

        // Composite indexes for hot query patterns
        Schema::table('client_exercise_logs', function (Blueprint $t) {
            $t->index(['client_id', 'workout_template_exercise_id', 'program_day_assignment_id'], 'cel_session_lookup');
            $t->index(['client_id', 'exercise_id'], 'cel_exercise_lookup');
        });

        Schema::table('personal_records', function (Blueprint $t) {
            $t->index(['user_id', 'exercise_id', 'record_type'], 'pr_user_exercise_type');
            $t->index(['user_id', 'exercise_id', 'achieved_at'], 'pr_user_exercise_date');
        });

        Schema::table('workout_session_reviews', function (Blueprint $t) {
            $t->index(['program_day_assignment_id', 'user_id'], 'wsr_assignment_user');
        });

        Schema::table('program_client_assignments', function (Blueprint $t) {
            $t->index(['client_id', 'activo'], 'pca_client_active');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('training_programs', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('exercises', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('program_day_assignments', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('workout_template_blocks', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('workout_template_exercises', fn (Blueprint $t) => $t->dropSoftDeletes());

        Schema::table('client_exercise_logs', function (Blueprint $t) {
            $t->dropIndex('cel_session_lookup');
            $t->dropIndex('cel_exercise_lookup');
        });
        Schema::table('personal_records', function (Blueprint $t) {
            $t->dropIndex('pr_user_exercise_type');
            $t->dropIndex('pr_user_exercise_date');
        });
        Schema::table('workout_session_reviews', fn (Blueprint $t) => $t->dropIndex('wsr_assignment_user'));
        Schema::table('program_client_assignments', fn (Blueprint $t) => $t->dropIndex('pca_client_active'));
    }
};
