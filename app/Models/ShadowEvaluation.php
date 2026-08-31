<?php

namespace App\Models;

use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.6, modo sombra).
class ShadowEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'exercise_id', 'workout_session_review_id', 'rule_id', 'proposed_weight', 'proposed_reps',
        'status', 'generated_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'status'          => TargetStatus::class,
        'proposed_weight'  => 'float',
        'proposed_reps'    => 'integer',
        'generated_at'     => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function rule()
    {
        return $this->belongsTo(SessionProgressionRule::class, 'rule_id', 'id');
    }
}
