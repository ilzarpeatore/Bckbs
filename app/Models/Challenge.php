<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'title', 'description', 'metric_type',
        'target_metric', 'start_date', 'end_date', 'scope',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function scores()
    {
        return $this->hasMany(ChallengeScore::class, 'challenge_id', 'id')
            ->orderBy('rank');
    }

    public function scopeActive($query)
    {
        return $query->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
    }
}
