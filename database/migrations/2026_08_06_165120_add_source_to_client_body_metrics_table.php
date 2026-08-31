<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_body_metrics', function (Blueprint $table) {
            $table->enum('source', ['client', 'coach'])->default('client')->after('client_id');
            $table->foreignId('recorded_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_body_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn('source');
        });
    }
};
