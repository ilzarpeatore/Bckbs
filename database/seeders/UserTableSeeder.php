<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'username' => 'systemadmin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
                'user_type' => 'admin',
                'status' => 'active',
                'display_name' => 'System Admin'
            ],
        ];

        foreach ($users as $key => $value) {
            $user = User::create($value);
            $user->assignRole($value['user_type']);
        }
    }
}
