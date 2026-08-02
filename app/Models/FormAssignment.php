<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'client_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'form_assignment_id', 'id');
    }
}
