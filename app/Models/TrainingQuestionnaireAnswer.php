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
        'training_days_per_week',
        'session_duration_preference',
        'training_mindset',
        'previous_coaching',
        'current_routine_style',
        'weekly_split_preference',
        'technique_level',
        'realistic_goal',
    ];

    protected $casts = [
        'training_experience_months' => 'integer',
        'training_days_per_week'     => 'integer',
        'technique_level'            => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
