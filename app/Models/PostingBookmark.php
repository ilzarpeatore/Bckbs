<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostingBookmark extends Model
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
    
    public function posting()
    {
        return $this->belongsTo(Posting::class, 'posting_id', 'id');
    }
    
    public function scopeMyBookmark($query)
    {
        $user = auth()->user();
              
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id)
                        ->whereDoesntHave('posting.reportPosting', function ($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
        }

        return $query;
    }
}
