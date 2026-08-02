<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssignDiet extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['user_id','diet_id'];

    protected $casts = [
            'user_id'   => 'integer',
            'diet_id'   => 'integer',
        ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function diet()
    {
        return $this->belongsTo(Diet::class, 'diet_id', 'id');
    }

    public function scopeMyAssignDiet($query)
    {
        $user = auth('sanctum')->user();

        return $query->where('user_id', $user->id);
        
    }
}
