<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientFeatureSetting extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'feature_key', 'is_enabled'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    /**
     * Si no existe fila para esta feature+cliente, se asume habilitada
     * por defecto (opt-out, no opt-in).
     */
    public static function isEnabledFor(int $client_id, string $feature_key): bool
    {
        $setting = static::where('client_id', $client_id)
            ->where('feature_key', $feature_key)
            ->first();

        return $setting ? $setting->is_enabled : true;
    }
}
