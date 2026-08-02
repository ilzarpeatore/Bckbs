<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    use HasFactory;

    protected $table = 'metrics_catalog';

    protected $fillable = ['key', 'label', 'unit', 'input_type', 'higher_is_better', 'order'];

    protected $casts = [
        'higher_is_better' => 'boolean',
        'order'            => 'integer',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
