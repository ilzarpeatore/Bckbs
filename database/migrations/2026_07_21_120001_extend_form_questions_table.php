<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_questions', function (Blueprint $table) {
            $table->json('options')->nullable()->after('type');
            $table->unsignedInteger('max_files')->nullable()->after('options');
            $table->foreignId('metric_id')->nullable()->after('max_files')->constrained('metrics_catalog')->nullOnDelete();
            $table->string('sync_type')->nullable()->after('metric_id'); // progress_photos | metric
            $table->boolean('allow_multiple')->default(false)->after('sync_type');
            $table->string('placeholder')->nullable()->after('allow_multiple');
            $table->unsignedInteger('scale_max')->default(10)->after('placeholder');
            $table->unsignedInteger('star_max')->default(5)->after('scale_max');
        });
    }

    public function down(): void
    {
        Schema::table('form_questions', function (Blueprint $table) {
            $table->dropColumn(['options', 'max_files', 'metric_id', 'sync_type', 'allow_multiple', 'placeholder', 'scale_max', 'star_max']);
        });
    }
};
