<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseCalibration extends Model
{
    use HasFactory;

    // La tabla real es 'client_exercise_calibration' (singular, tal como la
    // nombra el documento) - Eloquent pluralizaria "Calibration" a
    // "Calibrations" por defecto, hay que forzar el nombre exacto.
    protected $table = 'client_exercise_calibration';

    // Umbral global por defecto (documento §2.4) — Fase 2 podrá sobreescribirlo
    // por regla; en Fase 1 solo existe este default global.
    public const DEFAULT_MIN_SESSIONS = 2;

    protected $fillable = [
        'client_id', 'exercise_id', 'sessions_completed', 'calibration_complete',
    ];

    protected $casts = [
        'sessions_completed'    => 'integer',
        'calibration_complete'  => 'boolean',
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
     * Incrementa el contador de sesiones completadas con este ejercicio y
     * marca calibration_complete cuando alcanza el mínimo. Idempotente por
     * fila (updateOrCreate por client_id+exercise_id), pero cada llamada
     * SÍ incrementa — el llamador (SessionInterpretationService) es
     * responsable de no llamarlo más de una vez por sesión+ejercicio real.
     */
    public static function registerSessionCompleted(int $clientId, int $exerciseId, int $minSessions = self::DEFAULT_MIN_SESSIONS): self
    {
        $row = self::firstOrCreate(
            ['client_id' => $clientId, 'exercise_id' => $exerciseId],
            ['sessions_completed' => 0, 'calibration_complete' => false]
        );

        if (!$row->calibration_complete) {
            $row->sessions_completed += 1;
            if ($row->sessions_completed >= $minSessions) {
                $row->calibration_complete = true;
            }
            $row->save();
        }

        return $row;
    }
}
