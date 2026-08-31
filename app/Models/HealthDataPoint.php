<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthDataPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'source', 'metric_type', 'value', 'recorded_date', 'synced_at',
    ];

    protected $casts = [
        'value'         => 'float',
        'recorded_date' => 'date',
        'synced_at'     => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }
}
