<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    protected $casts = [
        'blocker_id' => 'integer',
        'blocked_id' => 'integer',
    ];

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id', 'id');
    }

    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id', 'id');
    }
}
