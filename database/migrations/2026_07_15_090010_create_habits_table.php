<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si client_id es null, es una plantilla reutilizable del coach.
     * Si tiene valor, es un hábito ya asignado a ese cliente concreto.
     */
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->decimal('target_value', 8, 2)->nullable();
            $table->string('target_unit')->nullable(); // min|steps|hours|...
            $table->string('frequency')->default('daily'); // daily|weekly
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
