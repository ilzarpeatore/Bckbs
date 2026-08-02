<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLimitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'type',
        'title',
        'description',
        'status',
        'date_reported',
        'date_resolved',
    ];

    protected $casts = [
        'date_reported' => 'date',
        'date_resolved' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
