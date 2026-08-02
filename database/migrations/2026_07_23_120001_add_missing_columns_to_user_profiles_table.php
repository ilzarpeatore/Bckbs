<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToUserProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('activity')->nullable()->after('address');
            $table->string('goal')->nullable()->after('activity');
            $table->string('macro_type')->nullable()->after('goal');
            $table->integer('carbs_pct')->nullable()->after('macro_type');
            $table->integer('protein_pct')->nullable()->after('carbs_pct');
            $table->integer('fat_pct')->nullable()->after('protein_pct');
            $table->text('water_reminder_settings')->nullable()->after('fat_pct');
            $table->text('meal_reminder_settings')->nullable()->after('water_reminder_settings');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'activity',
                'goal',
                'macro_type',
                'carbs_pct',
                'protein_pct',
                'fat_pct',
                'water_reminder_settings',
                'meal_reminder_settings',
            ]);
        });
    }
}
