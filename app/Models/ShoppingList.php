<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    protected $fillable = [
        'user_id', 'daily_plan_id', 'title', 'start_date', 'end_date', 'servings', 'status'
    ];

    protected $casts = [
        'user_id'       => 'integer',
        'daily_plan_id' => 'integer',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'servings'      => 'float',
    ];

    public function items()
    {
        return $this->hasMany(ShoppingListItem::class, 'shopping_list_id', 'id');
    }

    public function dailyPlan()
    {
        return $this->belongsTo(DailyPlan::class, 'daily_plan_id', 'id');
    }
    

    public function scopeMyShoppingList($query)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])){
            $query = $query->where('user_id', $user->id);
        }
        
        return $query;
    }
}
