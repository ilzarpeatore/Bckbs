<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostingLike extends Model
{
    protected $fillable = [ 'user_id', 'posting_id' ];

    protected $casts = [
        'user_id'       => 'integer',
        'posting_id'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    public function scopeMyLike($query)
    {
        $user = auth()->user();
              
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }

}
