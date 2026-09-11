<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Exercise extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasSlug, SoftDeletes;

    /**
     * Categoría de entrenamiento del ejercicio (filtro nuevo) — no confundir
     * con la columna `type` ya existente ('sets'/'duration', cómo se
     * registra el ejercicio en una sesión).
     */
    const EXERCISE_TYPES = [
        'fuerza'      => 'Fuerza',
        'movilidad'   => 'Movilidad',
        'pliometria'  => 'Pliometría',
        'metabolico'  => 'Metabólico',
        'cardio'      => 'Cardio',
    ];

    protected $fillable = [ 'title', 'slug', 'instruction', 'tips', 'video_type', 'video_url', 'bodypart_ids', 'duration', 'sets', 'equipment_id', 'level_id', 'status','is_premium', 'based', 'type', 'exercise_type', 'seconds_per_rep', 'increment_kg' ];

    protected $casts = [
        'equipment_id'      => 'integer',
        'level_id'          => 'integer',
        'is_premium'        => 'integer',
        'seconds_per_rep'   => 'integer',
        // Plan de Optimización, Ronda 13 ítem 40: incremento real de carga
        // de este ejercicio/equipo (mancuernas, máquina, barra...) --
        // usado por SessionProgressionRuleEngine como fallback antes que el
        // RoundingMode genérico de la regla, ver
        // SessionProgressionRuleEngine::applyRounding().
        'increment_kg'      => 'float',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id', 'id');
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'level_id', 'id');
    }

    public function getBodypartIdsAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null; 
    }

    public function setBodypartIdsAttribute($value)
    {
        $this->attributes['bodypart_ids'] = isset($value) ? json_encode($value) : null;
    }

    public function getSetsAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null;
    }
    
    public function setSetsAttribute($value)
    {
        $this->attributes['sets'] = isset($value) ? json_encode($value) : null;
    }
    
    public function workoutDayExercise(){
        return $this->hasMany(WorkoutDayExercise::class, 'exercise_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($row) {
            $row->workoutDayExercise()->delete();
        });
    }

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }

    public function isAccessible($subscription_system = null, $is_premium = 0, $user = null)
    {
        $is_accessible = false;

        if( $subscription_system == 1 && $is_premium) {
            if( $user != null && $user->is_subscribe) {
                $is_accessible = true;
            }
        } else {
            $is_accessible = true;
        }

        return $is_accessible;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');  
    }

}
