<?php

namespace App\Models;

use App\Enums\ConditionOperator;
use App\Enums\ConditionVariable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionProgressionRuleCondition extends Model
{
    use HasFactory;

    protected $table = 'session_progression_rule_conditions';

    protected $fillable = [
        'rule_id', 'variable', 'operator', 'threshold_value',
        'threshold_min', 'threshold_max', 'ventana_sesiones', 'logic_group',
    ];

    protected $casts = [
        'variable'         => ConditionVariable::class,
        'operator'          => ConditionOperator::class,
        'threshold_value'   => 'float',
        'threshold_min'     => 'float',
        'threshold_max'     => 'float',
        'ventana_sesiones'  => 'integer',
        'logic_group'       => 'integer',
    ];

    public function rule()
    {
        return $this->belongsTo(SessionProgressionRule::class, 'rule_id', 'id');
    }
}
