<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseFeedback extends Model
{
    use HasFactory;

    protected $table = 'client_exercise_feedback';

    protected $fillable = ['client_id', 'exercise_id', 'feedback'];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
