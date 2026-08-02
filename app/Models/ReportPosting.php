<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportPosting extends Model
{
   protected $fillable = [ 'user_id', 'posting_id', 'reason' ];

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

    public function scopeMyReportPost($query)
    {
        $user = auth()->user();
              
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }
}
