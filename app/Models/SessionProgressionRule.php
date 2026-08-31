<?php

namespace App\Models;

use App\Enums\FallbackBehavior;
use App\Enums\RuleMode;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Motor de Auto-Regulación de Carga — Fase 2. Regla configurable por el
 * coach. Ver nota de nombrado en la migración: NO es la tabla
 * "progression_rules" existente (esa es de progresión semanal por
 * programa, feature distinto).
 */
class SessionProgressionRule extends Model
{
    use HasFactory;

    protected $table = 'session_progression_rules';

    protected $fillable = [
        'coach_id', 'name', 'scope_type', 'scope_id', 'priority',
        'active', 'mode', 'fallback_behavior', 'shadow_mode',
    ];

    protected $casts = [
        'scope_type'        => ScopeType::class,
        'mode'               => RuleMode::class,
        'fallback_behavior'  => FallbackBehavior::class,
        'priority'           => 'integer',
        'active'             => 'boolean',
        'shadow_mode'        => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function conditions()
    {
        return $this->hasMany(SessionProgressionRuleCondition::class, 'rule_id', 'id');
    }

    public function action()
    {
        return $this->hasOne(SessionProgressionRuleAction::class, 'rule_id', 'id');
    }

    public function nextSessionTargets()
    {
        return $this->hasMany(NextSessionTarget::class, 'rule_id', 'id');
    }

    public function overrideLogs()
    {
        return $this->hasMany(OverrideLog::class, 'rule_id', 'id');
    }
}
