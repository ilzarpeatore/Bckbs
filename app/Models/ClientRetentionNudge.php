<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRetentionNudge extends Model
{
    protected $fillable = [
        'client_id', 'stage', 'episode_reference_date', 'sent_at', 'channel', 'status',
    ];

    protected $casts = [
        'episode_reference_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }
}
