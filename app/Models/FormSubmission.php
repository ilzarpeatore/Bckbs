<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['form_assignment_id', 'submitted_at', 'coach_feedback'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function formAssignment()
    {
        return $this->belongsTo(FormAssignment::class, 'form_assignment_id', 'id');
    }

    public function answers()
    {
        return $this->hasMany(FormAnswer::class, 'form_submission_id', 'id');
    }
}
