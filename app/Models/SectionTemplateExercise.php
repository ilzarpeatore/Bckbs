<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionTemplateExercise extends Model
{
    use HasFactory;

    protected $fillable = ['section_template_id', 'exercise_id', 'sequence', 'prescribed', 'enabled_metrics'];

    protected $casts = [
        'prescribed'      => 'array',
        'enabled_metrics' => 'array',
    ];

    public function section()
    {
        return $this->belongsTo(SectionTemplate::class, 'section_template_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
