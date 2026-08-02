<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo unificado de Check-Ins (recurrence != null) y Questionnaires
     * (recurrence = null, de una sola vez, ej. PAR-Q, consentimiento).
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('recurrence')->nullable(); // null|daily|weekly|biweekly|monthly
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
