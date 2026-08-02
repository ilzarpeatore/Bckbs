<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReadinessCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'date', 'sleep_quality', 'soreness_level', 'energy_level', 'stress_level',
    ];

    protected $casts = [
        'date'           => 'date',
        'sleep_quality'  => 'integer',
        'soreness_level' => 'integer',
        'energy_level'   => 'integer',
        'stress_level'   => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
