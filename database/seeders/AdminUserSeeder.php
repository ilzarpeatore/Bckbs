<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = new User();
        $user->first_name = 'Admin';
        $user->last_name = 'MightyFitness';
        $user->email = 'admin@mightyfitness.com';
        $user->username = 'admin';
        $user->password = bcrypt('password');
        $user->user_type = 'admin';
        $user->status = 'active';
        $user->display_name = 'Admin';
        $user->email_verified_at = now();
        $user->save();

        $user->assignRole('admin');

        $this->command?->info('Admin creado: admin@mightyfitness.com / password');
    }
}
