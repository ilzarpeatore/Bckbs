<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cliente del seguimiento de pagos sin cuenta en la app (ver migración
 * create_subscription_payment_clients_table).
 */
class SubscriptionPaymentClient extends Model
{
    protected $fillable = ['name', 'email', 'monthly_fee', 'notes', 'created_by'];

    protected $casts = [
        'monthly_fee' => 'float',
    ];

    public function records()
    {
        return $this->hasMany(SubscriptionPaymentRecord::class, 'external_client_id', 'id');
    }
}
