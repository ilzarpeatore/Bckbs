<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('challenge_id');
            $table->unsignedBigInteger('client_id');
            $table->decimal('current_value', 10, 2)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->foreign('challenge_id')->references('id')->on('challenges')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['challenge_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_scores');
    }
};
