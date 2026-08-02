<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\CommonNotification;

class Comment extends Model
{
    use HasFactory;
    protected $fillable = [ 'comment', 'posting_id', 'user_id'];

    protected $casts = [
        'user_id'       => 'integer',
        'posting_id'    => 'integer',
    ];

    public function posting()
    {
        return $this->belongsTo(Posting::class, 'posting_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeMyComment($query)
    {
        $user = auth()->user();

        if( request('user_id') ) {
            return $query->where('user_id', request('user_id'));
        }

        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function getCanEditAttribute()
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $this->user_id == $user->id;
    }

    public function getCanDeleteAttribute()
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($this->user_id == $user->id) {
            return true;
        }

        return $this->posting->user_id == $user->id;
    }

    public function commentReply()
    {
        return $this->hasMany(CommentReply::class, 'comment_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($row) {
            $row->commentReply()->delete();
        });

        static::saved(function ($comment) {
            $comment_username = optional($comment->user)->display_name;
            $posting = $comment->posting;
            
            if( !$posting || !$posting->user) {
                return;
            }
            if( $posting->user_id == $comment->user_id ) {
                return;
            }
            $notification_data = [
                'id'        => $comment->posting_id,
                'posting_id'=> $comment->posting_id,
                'type'      => 'comment',
                'subject'   => __('message.user_commented_posting', [ 'user' => $comment_username ]),
                'message'   => $comment->comment,
            ];
            $posting->user->notify(new CommonNotification($notification_data['type'], $notification_data));
        });
    }

    public function scopeCanBeDeletedBy($query)
    {
        $user = auth()->user();

        if (!$user) return $query->whereNull('id');

        // admin can delete anything
        if ($user->hasRole(['admin'])) {
            return $query;
        }

        return $query->where(function($q1) use ($user) {
            $q1->where('user_id', $user->id)
            ->orWhereHas('posting', function ($p) use ($user) {
                $p->where('user_id', $user->id);
            });
        });
    }
}
