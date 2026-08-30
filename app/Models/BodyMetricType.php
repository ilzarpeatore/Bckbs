<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo dinámico de tipos de medida corporal (antropometría) — value/label/
 * unit configurables desde el admin panel (UserDetailView.tsx, sección
 * Métricas del perfil de cliente), consumido tanto por
 * Admin\ClientBodyMetricController como por API\BodyMetricController (cliente)
 * para validar y listar qué metric_type existen. 'global' = disponible para
 * todos los clientes; 'client' = tipo custom creado solo para un cliente
 * concreto (ej. una métrica de un deporte específico).
 */
class BodyMetricType extends Model
{
    use HasFactory;

    protected $fillable = ['value', 'label', 'unit', 'scope', 'client_id', 'order'];

    protected $casts = ['order' => 'integer'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeVisibleTo(Builder $query, ?int $clientId): Builder
    {
        return $query->where(function (Builder $q) use ($clientId) {
            $q->where('scope', 'global');
            if ($clientId) {
                $q->orWhere(function (Builder $q2) use ($clientId) {
                    $q2->where('scope', 'client')->where('client_id', $clientId);
                });
            }
        });
    }
}
