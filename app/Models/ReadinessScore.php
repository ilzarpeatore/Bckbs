<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadinessScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'date', 'hrv_z_score', 'sueno_z_score', 'subjetivo_score',
        'acwr', 'combined_score', 'band', 'calculated_at',
    ];

    protected $casts = [
        'date'            => 'date',
        'hrv_z_score'     => 'float',
        'sueno_z_score'   => 'float',
        'subjetivo_score' => 'float',
        'acwr'            => 'float',
        'combined_score'  => 'float',
        'calculated_at'   => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }
}
