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
        Schema::create('meal_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['sequential', 'weekday'])
                ->comment('sequential = Day 1, Day 2... (no fixed length); weekday = Monday..Sunday, repeats weekly');
            $table->unsignedBigInteger('coach_id')->nullable()->comment('admin/coach who created this template');
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_templates');
    }
};
