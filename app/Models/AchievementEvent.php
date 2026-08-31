<?php

namespace App\Models;

use App\Enums\AchievementEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2). Capa de
 * feed/historial de logros, gateada a paid-tier — ver docblock de la
 * migración para la decisión de reconciliación con personal_records.
 */
class AchievementEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'type', 'exercise_id', 'value', 'previous_best',
        'significancia_verificada', 'shown_to_client', 'source_type', 'source_id',
    ];

    protected $casts = [
        'type'                       => AchievementEventType::class,
        'value'                      => 'float',
        'previous_best'              => 'float',
        'significancia_verificada'   => 'boolean',
        'shown_to_client'            => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    /**
     * Resuelve el modelo de origen (PersonalRecord|ClientExerciseLog|null)
     * a partir de source_type/source_id — referencia polimórfica manual
     * (sin FK real, distintas tablas de destino según el tipo de logro).
     */
    public function source(): ?Model
    {
        if (!$this->source_type || !$this->source_id) {
            return null;
        }

        return $this->source_type::find($this->source_id);
    }
}
