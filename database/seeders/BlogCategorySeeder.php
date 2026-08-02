<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['title' => 'Entrenamiento', 'slug' => 'entrenamiento', 'status' => 'active'],
            ['title' => 'Nutrición',     'slug' => 'nutricion',     'status' => 'active'],
            ['title' => 'Hábitos',       'slug' => 'habitos',       'status' => 'active'],
        ];

        foreach ($categories as $cat) {
            DB::table('blog_categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                array_merge($cat, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
