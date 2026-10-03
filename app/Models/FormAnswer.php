<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class FormAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['form_submission_id', 'form_question_id', 'answer_value'];

    // Fotos de progreso subidas desde un check-in (2026-10-03): se guardan
    // como "progress-photo:<media_id>" y se convierten aquí en una URL
    // firmada nueva cada vez que se lee la respuesta (el disco 'private' no
    // tiene URL pública y una firma guardada caducaría). Ver
    // ProgressPhotoService y FormController::submit().
    public function getAnswerValueAttribute($value)
    {
        if (!is_string($value) || !str_contains($value, 'progress-photo:')) {
            return $value;
        }

        return preg_replace_callback('/progress-photo:(\d+)/', fn ($m) => URL::temporarySignedRoute(
            'progress-photo.signed', now()->addHours(6), ['media' => (int) $m[1]]
        ), $value);
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id', 'id');
    }

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'form_question_id', 'id');
    }
}
