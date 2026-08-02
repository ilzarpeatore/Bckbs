<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Racha (streak) y % de cumplimiento semanal se calculan sobre esta
     * tabla en el momento de consultarlos — no tienen columna propia.
     */
    public function up(): void
    {
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('habit_id');
            $table->date('date');
            $table->decimal('value_logged', 8, 2)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('habit_id')->references('id')->on('habits')->onDelete('cascade');
            $table->unique(['habit_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};
