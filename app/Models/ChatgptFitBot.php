<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatgptFitBot extends Model
{
    use HasFactory;
    protected $fillable = [ 'user_id', 'question', 'answer' ];

    protected $casts = [
        'user_id'      => 'integer',
    ];
}
