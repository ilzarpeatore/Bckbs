<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GameScoreData extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [ 'user_id', 'score', 'country_code'];

    protected $casts = [
        'user_id'      => 'integer',
        'score'        => 'integer',
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}