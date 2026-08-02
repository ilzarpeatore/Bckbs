<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_program_id', 'week_number', 'load_multiplier',
        'is_deload', 'allow_special_techniques',
    ];

    protected $casts = [
        'load_multiplier'          => 'decimal:2',
        'is_deload'                => 'boolean',
        'allow_special_techniques' => 'boolean',
    ];

    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id', 'id');
    }
}
