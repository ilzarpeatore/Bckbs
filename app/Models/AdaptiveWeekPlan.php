<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdaptiveWeekPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'original_week_start', 'sessions_available', 'priorizacion',
        'mesocycle_extension', 'status', 'details',
    ];

    protected $casts = [
        'original_week_start' => 'date',
        'sessions_available'  => 'integer',
        'mesocycle_extension' => 'boolean',
        'details'             => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }
}
