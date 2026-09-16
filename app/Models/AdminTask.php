<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTask extends Model
{
    protected $table = 'admin_tasks';

    protected $fillable = [
        'type', 'title', 'description', 'status',
        'category', 'priority', 'due_date', 'client_id', 'created_by',
        'source_key', 'source_repo', 'source_url',
        'completed_at',
    ];

    protected $casts = [
        'client_id'    => 'integer',
        'created_by'   => 'integer',
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
