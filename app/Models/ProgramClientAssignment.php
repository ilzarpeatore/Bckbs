<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramClientAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['training_program_id', 'client_id', 'start_date', 'activo', 'source_subscription_id'];

    protected $casts = [
        'start_date' => 'date',
        'activo'     => 'boolean',
        'source_subscription_id' => 'integer',
    ];

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
