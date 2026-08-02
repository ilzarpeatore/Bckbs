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
        Schema::create('daily_steps_goals', function (Blueprint $table) {
            $table->id();
            $table->double('value')->nullable();
            $table->double('goal_value')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->foreignId('user_id')->cascade('delete');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_steps_goals');
    }
};
