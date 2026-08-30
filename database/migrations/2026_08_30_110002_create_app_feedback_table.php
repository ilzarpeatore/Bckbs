<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback in-app (item 5 del backlog): feature_request | bug_report que
     * el usuario manda desde la app, opcionalmente con un log de
     * diagnóstico. `status` lo gestiona el admin (open|reviewed|closed).
     */
    public function up(): void
    {
        Schema::create('app_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // feature_request|bug_report
            $table->string('title', 100);
            $table->text('description');
            $table->string('section'); // workout|nutrition|habits|metrics|other
            $table->string('section_other')->nullable();
            $table->longText('diagnostics_log')->nullable();
            $table->string('app_version')->nullable();
            $table->string('platform')->nullable(); // ios|android
            $table->string('status')->default('open'); // open|reviewed|closed
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
            $table->index('type');
            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_feedback');
    }
};
