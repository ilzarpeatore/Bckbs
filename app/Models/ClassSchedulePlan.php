<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSchedulePlan extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_id', 'class_schedule_id' ];

    protected $casts = [
        'user_id'   => 'integer',
        'class_schedule_id'   => 'integer',
    ];

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id', 'id');
    }
}
