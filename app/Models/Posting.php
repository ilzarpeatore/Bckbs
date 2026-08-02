<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Posting extends Model implements HasMedia
{
    use HasFactory,InteractsWithMedia;

    protected $fillable = [ 'description', 'status', 'user_id'];

    protected $casts = [
        'user_id'   => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeMyPosting($query)
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
    
    public function postingLike() {
        return $this->hasMany(PostingLike::class, 'posting_id', 'id');
    }

    public function comment()
    {
        return $this->hasMany(Comment::class, 'posting_id', 'id');
    }

    public function postingBookmark() {
        return $this->hasMany(PostingBookmark::class, 'posting_id', 'id');
    }

    public function reportPosting() {
        return $this->hasMany(ReportPosting::class, 'posting_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($row) {
            $row->postingLike()->delete();
            $row->postingBookmark()->delete();
            $row->comment()->delete();
            $row->reportPosting()->delete();
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeExcludeReportedPost($query) {
        $user = auth()->user();
        if ( $user ) {
            return $query->whereDoesntHave('reportPosting', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query;
    }

    public function getCanEditAttribute()
    {
        return $this->user_id == optional(auth()->user())->id;
    }

    public function getIsLikedAttribute()
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $this->postingLike()->where('user_id', $user->id)->exists();
    }

    public function getIsBookmarkAttribute()
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $this->postingBookmark()->where('user_id', $user->id)->exists();
    }

    public function getPostingMediaAttribute()
    {
        return getAttachmentArray( $this->getMedia('posting_media'), null);
    }
    public function scopeOrderByMyBookmarkDesc($query)
    {
        return $query
            ->whereHas('postingBookmark', fn ($q) => $q->myBookmark())
            ->withMax(
                ['postingBookmark as bookmarked_at' => fn ($q) => $q->myBookmark()],
                'created_at'
            )
            ->orderByDesc('bookmarked_at');
    }
}
