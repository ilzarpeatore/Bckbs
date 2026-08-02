<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca un training_program como el "calendario personal" (invisible
     * en la biblioteca normal) de un cliente concreto — se crea solo la
     * primera vez que asignas algo directo desde su perfil, sin que
     * tengas que crearlo tú a mano.
     */
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('title');
            $table->unsignedBigInteger('personal_client_id')->nullable()->after('is_personal');
            $table->foreign('personal_client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropForeign(['personal_client_id']);
            $table->dropColumn(['is_personal', 'personal_client_id']);
        });
    }
};
