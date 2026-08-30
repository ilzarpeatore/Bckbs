<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionRiskScore extends Model
{
    protected $fillable = [
        'client_id', 'date', 'dias_inactividad', 'compliance_actual', 'compliance_anterior',
        'dias_desde_ultimo_logro', 'dolor_score', 'combined_score', 'band', 'calculated_at',
    ];

    protected $casts = [
        'date' => 'date',
        'dias_inactividad' => 'integer',
        'compliance_actual' => 'float',
        'compliance_anterior' => 'float',
        'dias_desde_ultimo_logro' => 'integer',
        'dolor_score' => 'float',
        'combined_score' => 'float',
        'calculated_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }
}
