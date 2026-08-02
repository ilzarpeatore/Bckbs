<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LanguageDefaultList extends Model
{
    use HasFactory;

    protected $fillable = [ 'default_language_name', 'default_language_code', 'default_language_country_code'];

    public function languagelist()
    {
        return $this->hasMany(LanguageList::class, 'language_id','id');
    }
    
}
