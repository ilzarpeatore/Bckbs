<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['form_submission_id', 'form_question_id', 'answer_value'];

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id', 'id');
    }

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'form_question_id', 'id');
    }
}
