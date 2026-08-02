<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGraph extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_id', 'value', 'type', 'unit', 'date'];

    protected $casts = [
        'user_id'   => 'integer',
    ];

    public function scopeMyGraph($query)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])){
            $query = $query->where('user_id', $user->id);
        }
        
        return $query;
    }

    public function scopeLatestPerType($query)
    {
        return $query->orderBy('date', 'desc')->get()->unique('type');
    }
}
