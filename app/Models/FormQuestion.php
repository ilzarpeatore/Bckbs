<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'question_text',
        'type',
        'options',
        'max_files',
        'metric_id',
        'sync_type',
        'allow_multiple',
        'placeholder',
        'scale_max',
        'star_max',
        'order',
        'is_required',
    ];

    protected $casts = [
        'is_required'    => 'boolean',
        'allow_multiple' => 'boolean',
        'options'        => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function metric()
    {
        return $this->belongsTo(Metric::class, 'metric_id');
    }

    public function answers()
    {
        return $this->hasMany(FormAnswer::class, 'form_question_id', 'id');
    }
}
