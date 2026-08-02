<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;

    protected $fillable = ['coach_id', 'title', 'description', 'recurrence', 'auto_assign_all_clients'];

    protected $casts = [
        'auto_assign_all_clients' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function questions()
    {
        return $this->hasMany(FormQuestion::class, 'form_id', 'id')->orderBy('order');
    }

    public function assignments()
    {
        return $this->hasMany(FormAssignment::class, 'form_id', 'id');
    }

    /** null = Questionnaire (una sola vez); con valor = Check-In recurrente. */
    public function scopeQuestionnaires($query)
    {
        return $query->whereNull('recurrence');
    }

    public function scopeCheckIns($query)
    {
        return $query->whereNotNull('recurrence');
    }

    /** Forms marcados para auto-asignarse a todo cliente nuevo en el registro. */
    public function scopeAutoAssignAllClients($query)
    {
        return $query->where('auto_assign_all_clients', true);
    }
}
