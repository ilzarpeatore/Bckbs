<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable=[ 'name', 'duration_unit', 'duration', 'price', 'description', 'status', 'training_program_id', 'meal_plan_template_id', 'grants_full_workout_library', 'grants_full_recipe_library' ];

    protected $casts = [
        'duration'      => 'integer',
        'price'         => 'double',
        'training_program_id'    => 'integer',
        'meal_plan_template_id'  => 'integer',
        'grants_full_workout_library' => 'boolean',
        'grants_full_recipe_library'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // AÑADIDO: puente contenido↔paquete. Ambos nullable — un Package puede ser
    // solo-entrenamiento, solo-nutrición, o combinado (ej. "Definición 3 meses").
    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id', 'id');
    }

    public function mealPlanTemplate()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id', 'id');
    }
}
