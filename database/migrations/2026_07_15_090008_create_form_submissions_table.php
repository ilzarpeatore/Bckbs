<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_assignment_id');
            $table->timestamp('submitted_at')->nullable();
            $table->text('coach_feedback')->nullable();
            $table->timestamps();

            $table->foreign('form_assignment_id')->references('id')->on('form_assignments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
