<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Notifications\CommonNotification;

class CommentReply extends Model
{
    protected $fillable = [ 'comment', 'comment_id', 'user_id' ];

    protected $casts = [
        'user_id'       => 'integer',
        'comment_id'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
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
        
        if( $this->comments && $this->comments->posting && $this->comments->posting->user_id == $user->id ) {
            return true;
        }

        return false;
    }

    public function scopeMyCommentReply($query)
    {
        $user = auth()->user();
        
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function comments()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }

    public function scopeCanBeDeletedBy($query)
    {
        $user = auth()->user();
        
        if (!$user) return $query->whereNull('id');

        // admin can delete anything
        if ($user->hasRole(['admin'])) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)

            // User can delete replies under posts they own
            ->orWhereHas('comments.posting', function ($post) use ($user) {
                $post->where('user_id', $user->id);
            });
        });
    }

    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($comment_reply) {
            $comment_username = optional($comment_reply->user)->display_name;

            $comment = $comment_reply->comments;
            
            if( !$comment || !$comment->user) {
                return;
            }

            if( $comment->user_id == $comment_reply->user_id ) {
                return;
            }
            
            $notification_data = [
                'id'        => $comment_reply->id,
                'comment_id'=> $comment_reply->comment_id,
                'posting_id'=> $comment->posting_id,
                'type'      => 'commentreply',
                'subject'   => __('message.user_replied_comment', [ 'user' => $comment_username ]),
                'message'   => $comment_reply->comment,
            ];
            $comment->user->notify(new CommonNotification($notification_data['type'], $notification_data));
        });
    }

}
