<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PainReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'exercise_id', 'program_day_assignment_id', 'workout_template_id',
        'set_number', 'tipo', 'localizacion', 'intensidad', 'momento',
    ];

    protected $casts = [
        'intensidad' => 'integer',
    ];

    public const TIPO_MOLESTIA_LEVE = 'molestia_leve';
    public const TIPO_DOLOR_AGUDO = 'dolor_agudo';
    public const TIPO_DOLOR_QUE_EMPEORA = 'dolor_que_empeora_durante_sesion';

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    /**
     * True si este reporte, por sí solo, debe bloquear el cálculo de
     * progresión del ejercicio en esta sesión (documento §1.3).
     */
    public function blocksProgression(): bool
    {
        return $this->tipo !== self::TIPO_MOLESTIA_LEVE || $this->intensidad >= 4;
    }
}
