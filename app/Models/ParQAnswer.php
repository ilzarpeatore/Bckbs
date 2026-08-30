<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParQAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parq_heart_condition',
        'parq_chest_pain_activity',
        'parq_chest_pain_rest_last_month',
        'parq_dizziness_balance',
        'parq_bone_joint_problem',
        'parq_bp_or_heart_medication',
        'parq_reason_not_to_exercise',
        'parq_fitness_level',
        'parq_medical_history',
        'parq_goals',
    ];

    protected $casts = [
        'parq_heart_condition'            => 'boolean',
        'parq_chest_pain_activity'        => 'boolean',
        'parq_chest_pain_rest_last_month' => 'boolean',
        'parq_dizziness_balance'          => 'boolean',
        'parq_bone_joint_problem'         => 'boolean',
        'parq_bp_or_heart_medication'     => 'boolean',
        'parq_reason_not_to_exercise'     => 'boolean',
        'parq_fitness_level'              => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
