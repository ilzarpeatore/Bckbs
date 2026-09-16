<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bloqueo de usuario, item 11 del roadmap (requisito para reactivar
// COMMUNITY_ENABLED junto con report_comments, ver
// docs/PENDIENTE_BACKEND_ADMIN.md en el repo bsa). blocker = quien bloquea,
// blocked = a quien se bloquea -- unique() evita bloquear dos veces al mismo
// usuario (el segundo intento simplemente no-opea en el controller).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blocker_id');
            $table->unsignedBigInteger('blocked_id');
            $table->foreign('blocker_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('blocked_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['blocker_id', 'blocked_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_users');
    }
};
