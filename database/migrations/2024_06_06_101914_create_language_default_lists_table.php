<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CreateLanguageDefaultListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('language_default_lists', function (Blueprint $table) {
            $table->id();
            $table->string('default_language_name')->nullable();
            $table->string('default_language_code')->nullable();
            $table->string('default_language_country_code')->nullable();
            $table->timestamps();
        });

        $default_language = json_decode(File::get(public_path('json/languagedefaultlist.json')),true);    
        foreach ($default_language as $item) {
            DB::table('language_default_lists')->insert([
                'default_language_name' => $item['default_language_name'],
                'default_language_code' => $item['default_language_code'],
                'default_language_country_code' => $item['default_language_country_code'],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('language_default_lists');
    }
}
