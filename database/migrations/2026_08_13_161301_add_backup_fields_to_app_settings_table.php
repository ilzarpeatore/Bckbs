<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBackupFieldsToAppSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('backup_enabled')->default(false);
            $table->string('backup_frequency')->default('daily')->comment('daily, weekly');
            $table->unsignedInteger('backup_retention_days')->default(14);
            // Solo los escribe el propio comando backup:run, nunca el
            // formulario de ajustes (no van en el $fillable usado por
            // updateAppSettings) - son estado, no configuracion.
            $table->timestamp('backup_last_run_at')->nullable();
            $table->string('backup_last_status')->nullable()->comment('success, failed');
            $table->unsignedInteger('backup_last_size_kb')->nullable();
        });
    }

    public function down()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'backup_enabled', 'backup_frequency', 'backup_retention_days',
                'backup_last_run_at', 'backup_last_status', 'backup_last_size_kb',
            ]);
        });
    }
}
