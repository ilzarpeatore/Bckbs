<?php

namespace App\Models;

use App\Enums\ActionType;
use App\Enums\BaseReference;
use App\Enums\RoundingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionProgressionRuleAction extends Model
{
    use HasFactory;

    protected $table = 'session_progression_rule_actions';

    protected $fillable = ['rule_id', 'type', 'value', 'rounding', 'base_reference'];

    protected $casts = [
        'type'            => ActionType::class,
        'value'            => 'float',
        'rounding'         => RoundingMode::class,
        'base_reference'   => BaseReference::class,
    ];

    public function rule()
    {
        return $this->belongsTo(SessionProgressionRule::class, 'rule_id', 'id');
    }
}
