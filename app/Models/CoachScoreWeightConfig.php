<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachScoreWeightConfig extends Model
{
    protected $fillable = [
        'coach_id', 'score_type', 'w1', 'w2', 'w3', 'w4', 'auto_reengagement_enabled',
        'msg_dia_7', 'msg_dia_14', 'msg_dia_20',
    ];

    protected $casts = [
        'w1' => 'float', 'w2' => 'float', 'w3' => 'float', 'w4' => 'float',
        'auto_reengagement_enabled' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }
}
