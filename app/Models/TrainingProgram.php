<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgram extends Model
{
    use HasFactory, SoftDeletes;

    // AÑADIDO: is_personal, personal_client_id (calendario personal por cliente)
    protected $fillable = [
        'title', 'is_personal', 'personal_client_id', 'workout_id', 'coach_id', 'client_id',
        'num_weeks', 'fecha_inicio', 'fecha_fin', 'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'activo'       => 'boolean',
        'is_personal'  => 'boolean',
    ];

    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id', 'id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function progressionRules()
    {
        return $this->hasMany(ProgressionRule::class, 'training_program_id', 'id')
            ->orderBy('week_number');
    }

    public function clientAssignments()
    {
        return $this->hasMany(ProgramClientAssignment::class, 'training_program_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    // AÑADIDO: solo plantillas normales, sin los calendarios personales (para listados/biblioteca)
    public function scopeLibrary($query)
    {
        return $query->where('is_personal', false);
    }
}
