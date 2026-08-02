<?php

namespace Database\Seeders;

use App\Models\LanguageDefaultList;
use App\Models\LanguageVersionDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;



class LanguageDefaultListSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        //languagelist
        $json = File::get(public_path('json/languagedefaultlist.json'));
        $data = json_decode($json, true);

        foreach ($data as $item) {
            LanguageDefaultList::create([
                'default_language_name' => $item['default_language_name'],
                'default_language_code' => $item['default_language_code'],
                'default_language_country_code' => $item['default_language_country_code'],
            ]);
        }
       
        //version
        $language_version = LanguageVersionDetail::create([
            'version_no' => '1',
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
       
    }
}