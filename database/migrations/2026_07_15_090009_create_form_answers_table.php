<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_submission_id');
            $table->unsignedBigInteger('form_question_id');
            $table->text('answer_value')->nullable();
            $table->timestamps();

            $table->foreign('form_submission_id')->references('id')->on('form_submissions')->onDelete('cascade');
            $table->foreign('form_question_id')->references('id')->on('form_questions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
    }
};
