<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'client_id', 'title', 'type', 'content', 'external_url', 'scope',
        'image_url', 'category',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    /** Recursos compartidos: visibles para todo el roster del coach. */
    public function scopeShared($query)
    {
        return $query->where('scope', 'shared');
    }

    /** Recursos personales de un cliente concreto. */
    public function scopePersonalFor($query, $client_id)
    {
        return $query->where('scope', 'personal')->where('client_id', $client_id);
    }

    /** Lo que un cliente concreto debe ver: sus compartidos + los suyos personales. */
    public function scopeVisibleTo($query, $client_id)
    {
        return $query->where(function ($q) use ($client_id) {
            $q->where('scope', 'shared')
              ->orWhere(function ($q2) use ($client_id) {
                  $q2->where('scope', 'personal')->where('client_id', $client_id);
              });
        });
    }
}
