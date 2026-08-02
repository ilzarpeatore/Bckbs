<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PersonalClientInvite extends Model
{
    protected $fillable = [
        'code', 'first_name', 'last_name', 'email',
        'created_by_user_id', 'used_by_user_id', 'used_at', 'expires_at',
    ];

    protected $casts = [
        'used_at'   => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function generateUniqueCode(): string
    {
        do {
            // Formato corto y fácil de dictar/copiar a mano: XXXX-XXXX.
            $code = strtoupper(Str::random(4) . '-' . Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_user_id', 'id');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }
}
