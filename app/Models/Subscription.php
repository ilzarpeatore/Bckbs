<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
   
   protected $fillable = [ 'user_id', 'package_id', 'total_amount', 'payment_type', 'txn_id', 'transaction_detail', 'payment_status', 'subscription_start_date', 'subscription_end_date', 'package_data', 'status', 'callback', 'fulfilled_at', 'access_revoked_at' ];

    protected $casts = [
        'user_id'  => 'integer',
        'package_id'  => 'integer',
        'total_amount' => 'double',
        'fulfilled_at' => 'datetime',
        'access_revoked_at' => 'datetime',
    ];

    // AÑADIDO 2026-07-30: al quedar activa+pagada (creación o edición posterior),
    // dispara la importación del contenido del Package (TrainingProgram/MealPlanTemplate)
    // al calendario real del cliente. Idempotente vía fulfilled_at — funciona igual
    // sin importar quién cree/edite la Subscription (admin de prueba hoy, gateway de
    // pago real más adelante), sin tener que repetir la llamada en cada sitio nuevo.
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($subscription) {
            if (
                !$subscription->fulfilled_at
                && $subscription->status === config('constant.SUBSCRIPTION_STATUS.ACTIVE')
                && $subscription->payment_status === 'paid'
            ) {
                \App\Services\PackageFulfillmentService::fulfill($subscription);
            }
        });
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }

    public function getPackageDataAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null;
    }

    public function setPackageDataAttribute($value)
    {
        $this->attributes['package_data'] = isset($value) ? json_encode($value) : null;
    }

    public function getTransactionDetailAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null;
    }

    public function setTransactionDetailAttribute($value)
    {
        $this->attributes['transaction_detail'] = isset($value) ? json_encode($value) : null;
    }

    public function scopeMySubscription($query, $user_id =null)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])) {
            $query = $query->where('user_id', $user->id);
        }
        return $query;
    }

    public function getformatedTotalAmountAttribute()
    {
        return getPriceFormat($this->total_amount);
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');  
    }
}
