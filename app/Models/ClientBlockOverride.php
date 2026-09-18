<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloque entero añadido solo para un cliente, sin tocar la plantilla
 * compartida (workout_template_blocks) -- ver migración
 * create_client_block_overrides_table (auditoría 2026-09-18).
 */
class ClientBlockOverride extends Model
{
    use HasFactory;

    protected $fillable = ['program_day_assignment_id', 'client_id', 'title', 'instructions', 'order'];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProgramDayAssignment::class, 'program_day_assignment_id', 'id');
    }

    public function exercises()
    {
        return $this->hasMany(ClientExerciseOverride::class, 'client_block_override_id', 'id')
            ->orderBy('sequence');
    }
}
