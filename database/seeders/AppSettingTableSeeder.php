<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class AppSettingTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $appsetting = [
            'site_name' => 'Mighty Fitness',
            'site_email' => NULL,
            'site_description' => NULL,
            'site_copyright' => NULL,
            'facebook_url' => NULL,
            'instagram_url' => NULL,
            'twitter_url' => NULL,
            'linkedin_url' => NULL,
            'language_option' => ["en"],
            'contact_email' => NULL,
            'contact_number' => NULL,
            'help_support_url' => NULL,
            'color' => '#ec7e4a',
        ];
        
        AppSetting::create($appsetting);
    }
}