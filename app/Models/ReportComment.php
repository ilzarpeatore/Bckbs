<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Mismo patrón que ReportPosting.
class ReportComment extends Model
{
    protected $fillable = ['user_id', 'comment_id', 'reason'];

    protected $casts = [
        'user_id'    => 'integer',
        'comment_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }
}
