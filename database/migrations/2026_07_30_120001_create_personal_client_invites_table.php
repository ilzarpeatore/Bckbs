<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código de invitación que el coach genera desde el admin y comparte
     * a mano (WhatsApp, SMS, etc. — no hay deep-linking nativo configurado
     * en la app todavía, así que es un código corto, no un enlace que abra
     * la app directamente). El cliente lo introduce al registrarse y su
     * cuenta queda marcada is_personal_client=true automáticamente.
     */
    public function up(): void
    {
        Schema::create('personal_client_invites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('used_by_user_id')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('used_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_client_invites');
    }
};
