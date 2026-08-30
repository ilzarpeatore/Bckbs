<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'client_id', 'source_template_id', 'title', 'icon', 'category',
        'target_value', 'target_unit', 'frequency',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(HabitLog::class, 'habit_id', 'id');
    }

    /** Plantilla (biblioteca global) de la que este hábito de cliente se adoptó, si aplica. */
    public function sourceTemplate()
    {
        return $this->belongsTo(Habit::class, 'source_template_id', 'id');
    }

    /** Hábitos de cliente que se crearon adoptando esta plantilla — para ver adopción real desde el admin. */
    public function adoptions()
    {
        return $this->hasMany(Habit::class, 'source_template_id', 'id');
    }

    /** null = plantilla reutilizable del coach (biblioteca global); con valor = ya asignado/creado para ese cliente. */
    public function scopeTemplates($query)
    {
        return $query->whereNull('client_id');
    }

    public function scopeForClient($query, $client_id)
    {
        return $query->where('client_id', $client_id);
    }

    /**
     * Origen del hábito, para que admin y app puedan distinguir de un vistazo:
     * - 'coach_assigned': el coach lo creó y asignó directo (coach_id set, sin plantilla).
     * - 'library': el cliente lo adoptó de la biblioteca global (source_template_id set).
     * - 'personal': el propio cliente lo creó desde cero (coach_id null, sin plantilla).
     */
    public function getSourceTypeAttribute(): ?string
    {
        if ($this->client_id === null) {
            return null; // es una plantilla, no un hábito de cliente
        }
        if ($this->source_template_id !== null) {
            return 'library';
        }
        return $this->coach_id !== null ? 'coach_assigned' : 'personal';
    }

    /**
     * Racha actual (días consecutivos completados hasta hoy).
     * Se calcula sobre habit_logs, no se guarda como columna.
     */
    public function getCurrentStreakAttribute(): int
    {
        $logs = $this->logs()
            ->where('is_completed', true)
            ->orderByDesc('date')
            ->pluck('date');

        $streak = 0;
        $expected = now()->startOfDay();

        foreach ($logs as $date) {
            if ($date->isSameDay($expected)) {
                $streak++;
                $expected = $expected->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Determina is_completed al loguear un día: si el hábito tiene un
     * objetivo numérico (target_value) y se envía un value_logged, se
     * completa según si alcanza o supera ese objetivo — no según un booleano
     * suelto — así el streak, el % de cumplimiento y el color del heatmap
     * reflejan progreso real (p.ej. "6.000 de 10.000 pasos") en vez de solo
     * "se tocó el botón". Si el hábito no tiene objetivo numérico (binario,
     * como "Hacer la cama") o no se manda value_logged, se respeta el
     * booleano explícito recibido (o true por defecto, comportamiento previo).
     */
    public function resolveLogCompletion(?float $valueLogged, ?bool $explicitCompleted): bool
    {
        if ($this->target_value !== null && $valueLogged !== null) {
            return $valueLogged >= (float) $this->target_value;
        }
        return $explicitCompleted ?? true;
    }
}
