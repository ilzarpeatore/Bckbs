<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPaymentRecord extends Model
{
    protected $fillable = [
        'user_id', 'year', 'month', 'amount', 'paid', 'paid_at', 'notes', 'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'amount' => 'float',
        'paid' => 'boolean',
        'paid_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
