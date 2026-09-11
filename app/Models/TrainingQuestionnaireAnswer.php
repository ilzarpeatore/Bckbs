<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingQuestionnaireAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goal_type',
        'activity_level',
        'lifestyle_type',
        'training_experience_months',
        'training_experience_months_coach',
        'training_days_per_week',
        'session_duration_preference',
        'training_mindset',
        'previous_coaching',
        'current_routine_style',
        'weekly_split_preference',
        'technique_level',
        'technique_level_coach',
        'realistic_goal',
        'overridden_by_id',
        'overridden_at',
    ];

    protected $casts = [
        'training_experience_months'        => 'integer',
        'training_experience_months_coach'  => 'integer',
        'training_days_per_week'            => 'integer',
        'technique_level'                   => 'integer',
        'technique_level_coach'             => 'integer',
        'overridden_at'                      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function overriddenBy()
    {
        return $this->belongsTo(User::class, 'overridden_by_id', 'id');
    }

    /**
     * Plan de Optimización, Ronda 7 ítem 25 — valor que debe usar el motor
     * de reglas: el override del coach si existe (evaluación profesional
     * directa), si no el autoevaluado por el cliente en el onboarding.
     */
    public function effectiveExperienceMonths(): ?int
    {
        return $this->training_experience_months_coach ?? $this->training_experience_months;
    }

    public function effectiveTechniqueLevel(): ?int
    {
        return $this->technique_level_coach ?? $this->technique_level;
    }
}
