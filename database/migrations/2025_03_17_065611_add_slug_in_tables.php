<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{

    private array $tables = [
        'levels' => 'title',
        'body_parts' => 'title',
        'workouts' => 'title',
        'workout_types' => 'title',
        'exercises' => 'title',
        'equipment' => 'title',
        'diets' => 'title',
        'category_diets' => 'title',
        'products' => 'title',
        'product_categories' => 'title',
        'posts' => 'title',
        'categories' => 'title',
        'tags' => 'title',
        'quotes' => 'title',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {

        // Add slug column
        foreach ($this->tables as $table => $titleColumn) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('slug')->after('title')->nullable();
            });

            // Manually generate slugs only for NULL values
            DB::table($table)->whereNull('slug')->chunkById(100, function ($records) use ($table, $titleColumn) {
                foreach ($records as $record) {
                    $slug = Str::slug($record->$titleColumn);

                    // Ensure unique slug
                    $originalSlug = $slug;
                    $counter = 1;
                    while (DB::table($table)->where('slug', $slug)->exists()) {
                        $slug = $originalSlug . '-' . $counter;
                        $counter++;
                    }

                    // Directly update slug without triggering model events
                    DB::table($table)->where('id', $record->id)->update(['slug' => $slug]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
