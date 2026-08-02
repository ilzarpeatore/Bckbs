<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // PermissionTableSeeder::class,
            // RoleTableSeeder::class,
            UserTableSeeder::class,
            AppSettingTableSeeder::class,
            StandardProfileFormSeeder::class,
            BlogCategorySeeder::class,
        ]);
        
    }
}
