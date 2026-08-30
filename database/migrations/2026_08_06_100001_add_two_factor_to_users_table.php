<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_backup_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_backup_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
