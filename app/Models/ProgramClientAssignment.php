<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramClientAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_program_id', 'client_id', 'start_date', 'fecha_fin',
        'activo', 'cerrado_at', 'source_subscription_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'fecha_fin'  => 'date',
        'activo'     => 'boolean',
        'cerrado_at' => 'datetime',
        'source_subscription_id' => 'integer',
    ];

    /**
     * Cierre automático de mesociclo. fecha_fin se fija UNA VEZ al asignar
     * (o renovar) — nunca se recalcula al vuelo — para que una semana de
     * "modo vida real" (AdaptiveWeekPlanner) no pueda desplazarla: cuenta
     * como una semana normal del mesociclo, no como una extensión.
     */
    public static function computeFechaFin(Carbon $startDate, int $numWeeks): Carbon
    {
        return $startDate->copy()->addDays(($numWeeks * 7) - 1);
    }

    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    // AÑADIDO: de qué Subscription (compra de Package) vino esta asignación,
    // null si la hizo el coach a mano. Al expirar la Subscription, basta con
    // desactivar/borrar esta fila — los días concretos se resuelven en vivo
    // contra program_day_assignments (plantilla compartida), no hay que
    // limpiarlos uno a uno como en daily_plan_recipes.
    public function sourceSubscription()
    {
        return $this->belongsTo(Subscription::class, 'source_subscription_id', 'id');
    }
}
