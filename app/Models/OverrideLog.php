<?php

namespace App\Models;

use App\Enums\ActionTaken;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverrideLog extends Model
{
    use HasFactory;

    // Solo created_at (tabla de auditoría append-only, ver migración).
    const UPDATED_AT = null;

    protected $fillable = [
        'next_session_target_id', 'rule_id', 'client_id', 'exercise_id',
        'suggested_value', 'applied_value', 'action_taken', 'motivo',
    ];

    protected $casts = [
        'action_taken'      => ActionTaken::class,
        'suggested_value'   => 'float',
        'applied_value'      => 'float',
    ];

    public function target()
    {
        return $this->belongsTo(NextSessionTarget::class, 'next_session_target_id', 'id');
    }

    public function rule()
    {
        return $this->belongsTo(SessionProgressionRule::class, 'rule_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
