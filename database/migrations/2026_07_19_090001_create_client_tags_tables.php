<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title');
            $table->string('color')->default('#2e5cff'); // para pintarlo en el buscador
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('client_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_tag_id');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_tag_id')->references('id')->on('client_tags')->onDelete('cascade');
            $table->unique(['client_id', 'client_tag_id'], 'client_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tag_assignments');
        Schema::dropIfExists('client_tags');
    }
};
