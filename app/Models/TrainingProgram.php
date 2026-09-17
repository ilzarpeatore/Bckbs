<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgram extends Model
{
    use HasFactory, SoftDeletes;

    // AÑADIDO: is_personal, personal_client_id (calendario personal por cliente)
    // AÑADIDO: is_free_accessible, billing_plan_id (acceso gratuito / plan)
    // AÑADIDO: source, source_id (procedencia de imports de programas)
    // AÑADIDO: source_training_program_id, is_client_copy (linaje de clonado
    // por cliente, docs/PLAN_CLONADO_PROGRAMAS.md — Fase 1, aún sin usar)
    protected $fillable = [
        'title', 'is_personal', 'personal_client_id', 'workout_id', 'coach_id', 'client_id',
        'num_weeks', 'fecha_inicio', 'fecha_fin', 'activo',
        'is_free_accessible', 'billing_plan_id',
        'source', 'source_id',
        'source_training_program_id', 'is_client_copy',
    ];

    protected $casts = [
        'fecha_inicio'   => 'date',
        'fecha_fin'      => 'date',
        'activo'         => 'boolean',
        'is_personal'    => 'boolean',
        'is_client_copy' => 'boolean',
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

    public function clientAssignments()
    {
        return $this->hasMany(ProgramClientAssignment::class, 'training_program_id', 'id');
    }

    public function dayAssignments()
    {
        return $this->hasMany(ProgramDayAssignment::class, 'training_program_id', 'id');
    }

    // AÑADIDO: plantilla de biblioteca de la que viene este clon de cliente
    // (docs/PLAN_CLONADO_PROGRAMAS.md — Fase 1, aún sin poblar por ningún flujo)
    public function sourceTrainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'source_training_program_id', 'id');
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
