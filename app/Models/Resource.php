<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'title', 'type', 'content', 'external_url', 'scope', 'category',
    ];

    /** Sub-secciones validas por pestaña (scope) en la app. */
    public const CATEGORIES = [
        'entrenamiento', 'nutricion', 'habitos_mindset', // scope=shared
        'onboarding', 'planes_actuales',                 // scope=assigned
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    /** Clientes concretos a los que se asigno este recurso (scope='assigned'). */
    public function assignedClients()
    {
        return $this->belongsToMany(User::class, 'resource_assignments', 'resource_id', 'client_id')
            ->withPivot('assigned_at');
    }

    /** Recursos compartidos: visibles para todo el roster del coach. */
    public function scopeShared($query)
    {
        return $query->where('scope', 'shared');
    }

    /** Recursos asignados a un cliente concreto (via resource_assignments). */
    public function scopeAssignedTo($query, $client_id)
    {
        return $query->where('scope', 'assigned')
            ->whereHas('assignedClients', fn ($q) => $q->where('users.id', $client_id));
    }

    /** Lo que un cliente concreto debe ver: compartidos + los asignados a el. */
    public function scopeVisibleTo($query, $client_id)
    {
        return $query->where(function ($q) use ($client_id) {
            $q->where('scope', 'shared')
              ->orWhere(function ($q2) use ($client_id) {
                  $q2->where('scope', 'assigned')
                     ->whereHas('assignedClients', fn ($q3) => $q3->where('users.id', $client_id));
              });
        });
    }
}
