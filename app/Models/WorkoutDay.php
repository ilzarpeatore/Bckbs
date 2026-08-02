<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Http\Resources\WorkoutDayExerciseResource;

class WorkoutDay extends Model implements HasMedia
{
    use HasFactory,InteractsWithMedia;
    
    protected $fillable = [ 'workout_id', 'sequence', 'is_rest'];

    protected $casts = [
        'is_rest'       => 'integer',
        'workout_id'    => 'integer',
        'sequence'      => 'integer',
    ];
    
    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id', 'id');
    }

    public function workoutDayExercise()
    {
        return $this->hasMany(WorkoutDayExercise::class, 'workout_day_id', 'id');
    }

    public function blocks()
    {
        return $this->hasMany(WorkoutDayBlock::class, 'workout_day_id', 'id')->orderBy('order');
    }

    protected static function boot() {
        parent::boot();
        static::deleted(function ($row) {
            $row->workoutDayExercise()->delete();
            $row->blocks()->delete();
        });
    }

    public function getSequenceDayAttribute() {
        return (int) $this->sequence + 1;
    }

    public function getFullDayWithBlocks()
    {
        $blocks = $this->blocks()->with(['exercises.exercise'])->get()->map(function ($block) {
            return [
                'block_id'  => $block->id,
                'title'     => $block->title,
                'order'     => $block->order,
                'exercises' => $block->exercises->map(fn ($e) => new WorkoutDayExerciseResource($e))->values(),
            ];
        });

        $unassigned = $this->workoutDayExercise()
            ->whereNull('workout_day_block_id')
            ->orderBy('sequence')
            ->with('exercise')
            ->get();

        if ($unassigned->isNotEmpty()) {
            $blocks->push([
                'block_id'  => null,
                'title'     => 'Sin agrupar',
                'order'     => 999,
                'exercises' => $unassigned->map(fn ($e) => new WorkoutDayExerciseResource($e))->values(),
            ]);
        }

        return [
            'workout_day_id' => $this->id,
            'sequence'       => $this->sequence,
            'is_rest'        => $this->is_rest,
            'blocks'         => $blocks->values(),
        ];
    }

    public function scopeUpnextWorkout($query)
    {
        $workout_id     = request('workout_id');
        $workout_day_id = request('workout_day_id');
        $exercise_id    = request('exercise_id');
        $days_per_page  = request('per_page') ?? 3;

        // Step 1: Load current workout day
        $currentDay = null;

        if ($workout_day_id) {
            $currentDay = self::where('workout_id', $workout_id)
                ->where('id', $workout_day_id)
                ->first();
        }

        // Step 2: Resolve current exercise sequence
        $currentExerciseSequence = null;

        if ($currentDay && $exercise_id) {
            $currentExerciseSequence = WorkoutDayExercise::findWorkoutDayExercise($workout_id, $currentDay->id, $exercise_id)->value('sequence');
        }

        // Step 3: Detect if this is the LAST exercise of the day
        $isLastExerciseOfDay = false;
        if ($currentDay && $currentExerciseSequence !== null) {
            $lastSequence = WorkoutDayExercise::findWorkoutDayExercise($workout_id, $currentDay->id)->max('sequence');

            if ($currentExerciseSequence === $lastSequence) {
                $isLastExerciseOfDay = true;
            }
        }

        // Step 4: Decide starting day sequence
        if ($currentDay) {
            // Rest day → always skip
            if ($currentDay->is_rest == 1) {
                $startDaySequence = $currentDay->sequence + 1;

            // Last exercise completed → skip whole day
            } elseif ($isLastExerciseOfDay) {
                $startDaySequence = $currentDay->sequence + 1;

            // Otherwise continue same day
            } else {
                $startDaySequence = $currentDay->sequence;
            }
        } else {
            // No day provided → start from beginning
            $startDaySequence = 0;
        }

        // Step 5: Fetch workout days
        $workout_days = self::where('workout_id', $workout_id)
            ->where('sequence', '>=', $startDaySequence)
            ->orderBy('sequence')
            ->limit($days_per_page)
            ->get();

        // Step 6: Attach exercises
        
        $data = $workout_days->map(function ($day) use ( $workout_id, $currentDay, $currentExerciseSequence )
        {
            // Rest day → always empty
            if ($day->is_rest == 1) {
                return [
                    'day'      => $day->id,
                    'sequence' => $day->sequence,
                    'is_rest'  => 1,
                    'exercise' => [],
                ];
            }

            $query = WorkoutDayExercise::findWorkoutDayExercise($workout_id, $day->id)->with('exercise')
                ->orderBy('sequence');

            // Same day + exercise_id → skip completed exercises
            if ( $currentDay && $currentDay->id === $day->id && $currentExerciseSequence !== null ) {
                $query->where('sequence', '>', $currentExerciseSequence);
            }

            $exercises = $query->get()->map(fn ($row) => 
                    new WorkoutDayExerciseResource($row)
                )->values();

            // Safety: do not return empty workout days
            if ($exercises->isEmpty()) {
                return null;
            }

            return [
                'workout_day_id'    => $day->id,
                'sequence'          => $day->sequence,
                'is_rest'           => 0,
                'exercise'          => $exercises,
            ];
        })
        ->filter()->values();

        return $data;
    }
}