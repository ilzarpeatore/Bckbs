<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondsPerRepToExercisesTable extends Migration
{
    public function up()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->integer('seconds_per_rep')->nullable()->after('sets');
        });
    }

    public function down()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('seconds_per_rep');
        });
    }
}
