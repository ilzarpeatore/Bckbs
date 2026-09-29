<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preguntas nuevas del onboarding v2 (2026-09-29), para los datos que los
 * agentes de programación (AgenticdesignBS, perfil-cliente.schema.json)
 * necesitaban y nadie recogía:
 *
 * - par_q_answers: lesión/molestia principal estructurada (zona, gesto que
 *   duele, fase, si empeora con impacto, visto bueno profesional).
 * - training_questionnaire_answers: deporte/evento objetivo, contexto de vida
 *   (horario, sueño, estrés), dónde entrena y con qué material, y referencias
 *   de fuerza en 4 ejercicios.
 * - nutrition_questionnaire_answers: nutrición práctica (presupuesto, dónde
 *   come, horarios, ayuno, alcohol, agua, dietas previas).
 *
 * Todas nullable: las versiones de la app que aún no las preguntan siguen
 * funcionando, y en usuarios antiguos quedan vacías (= no preguntado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('par_q_answers', function (Blueprint $table) {
            $table->boolean('injury_has')->nullable()->after('parq_bone_joint_problem');
            $table->string('injury_zone', 30)->nullable()->after('injury_has');
            $table->text('injury_painful_movement')->nullable()->after('injury_zone');
            $table->string('injury_phase', 30)->nullable()->after('injury_painful_movement');
            $table->string('injury_worsens_with_impact', 10)->nullable()->after('injury_phase');
            $table->string('injury_professional_clearance', 20)->nullable()->after('injury_worsens_with_impact');
            $table->text('injury_other_notes')->nullable()->after('injury_professional_clearance');
        });

        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            $table->boolean('practices_other_sport')->nullable()->after('goal_type');
            $table->text('other_sport_description')->nullable()->after('practices_other_sport');
            $table->boolean('has_target_event')->nullable()->after('other_sport_description');
            $table->string('target_event_description')->nullable()->after('has_target_event');
            $table->date('target_event_date')->nullable()->after('target_event_description');
            $table->string('work_schedule', 30)->nullable()->after('lifestyle_type');
            $table->string('training_time_of_day', 20)->nullable()->after('work_schedule');
            $table->unsignedTinyInteger('sleep_hours')->nullable()->after('training_time_of_day');
            $table->string('sleep_regularity', 20)->nullable()->after('sleep_hours');
            $table->unsignedTinyInteger('stress_level')->nullable()->after('sleep_regularity');
            $table->string('training_location', 30)->nullable()->after('session_duration_preference');
            $table->json('home_equipment')->nullable()->after('training_location');
            $table->text('equipment_notes')->nullable()->after('home_equipment');
            $table->decimal('strength_squat_kg', 6, 2)->nullable()->after('technique_level');
            $table->unsignedTinyInteger('strength_squat_reps')->nullable()->after('strength_squat_kg');
            $table->decimal('strength_deadlift_kg', 6, 2)->nullable()->after('strength_squat_reps');
            $table->unsignedTinyInteger('strength_deadlift_reps')->nullable()->after('strength_deadlift_kg');
            $table->decimal('strength_db_bench_kg', 6, 2)->nullable()->after('strength_deadlift_reps');
            $table->unsignedTinyInteger('strength_db_bench_reps')->nullable()->after('strength_db_bench_kg');
            $table->decimal('strength_db_row_kg', 6, 2)->nullable()->after('strength_db_bench_reps');
            $table->unsignedTinyInteger('strength_db_row_reps')->nullable()->after('strength_db_row_kg');
        });

        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->string('weekly_food_budget', 20)->nullable()->after('cooks_for_others');
            $table->string('meals_away_from_home', 20)->nullable()->after('weekly_food_budget');
            $table->text('meal_schedule')->nullable()->after('meals_away_from_home');
            $table->boolean('intermittent_fasting')->nullable()->after('meal_schedule');
            $table->string('alcohol_frequency', 20)->nullable()->after('intermittent_fasting');
            $table->string('water_intake', 20)->nullable()->after('alcohol_frequency');
            $table->text('previous_diets')->nullable()->after('water_intake');
        });
    }

    public function down(): void
    {
        Schema::table('par_q_answers', function (Blueprint $table) {
            $table->dropColumn([
                'injury_has', 'injury_zone', 'injury_painful_movement', 'injury_phase',
                'injury_worsens_with_impact', 'injury_professional_clearance', 'injury_other_notes',
            ]);
        });

        Schema::table('training_questionnaire_answers', function (Blueprint $table) {
            $table->dropColumn([
                'practices_other_sport', 'other_sport_description', 'has_target_event',
                'target_event_description', 'target_event_date', 'work_schedule', 'training_time_of_day',
                'sleep_hours', 'sleep_regularity', 'stress_level', 'training_location', 'home_equipment',
                'equipment_notes', 'strength_squat_kg', 'strength_squat_reps', 'strength_deadlift_kg',
                'strength_deadlift_reps', 'strength_db_bench_kg', 'strength_db_bench_reps',
                'strength_db_row_kg', 'strength_db_row_reps',
            ]);
        });

        Schema::table('nutrition_questionnaire_answers', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_food_budget', 'meals_away_from_home', 'meal_schedule', 'intermittent_fasting',
                'alcohol_frequency', 'water_intake', 'previous_diets',
            ]);
        });
    }
};
