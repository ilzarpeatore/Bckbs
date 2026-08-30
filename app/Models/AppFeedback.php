<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppFeedback extends Model
{
    protected $table = 'app_feedback';

    protected $fillable = [
        'user_id', 'type', 'title', 'description', 'section', 'section_other',
        'diagnostics_log', 'app_version', 'platform', 'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
