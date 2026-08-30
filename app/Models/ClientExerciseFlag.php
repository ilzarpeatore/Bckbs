<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'exercise_id', 'localizacion', 'pain_pattern_flag', 'flagged_at',
    ];

    protected $casts = [
        'pain_pattern_flag' => 'boolean',
        'flagged_at'        => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
