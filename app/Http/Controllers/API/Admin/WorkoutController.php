<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Workout;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayBlock;
use App\Models\WorkoutDayExercise;
use App\Http\Resources\WorkoutResource;
use Illuminate\Http\Request;

class WorkoutController extends BaseController
{
    protected function getModelClass(): string
    {
        return Workout::class;
    }

    protected function getResourceClass(): string
    {
        return WorkoutResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'           => 'required|string|max:255',
            'slug'            => 'sometimes|string|max:255|unique:workouts,slug,' . $id,
            'description'     => 'nullable|string',
            'workout_type_id' => 'required|exists:workout_types,id',
            'level_id'        => 'required|exists:levels,id',
            'is_premium'      => 'sometimes|boolean',
            'visibility'      => 'sometimes|in:all,premium',
            'status'          => 'sometimes|in:active,inactive',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('image');
            $item->addMediaFromRequest('image')->toMediaCollection('image');
        }
    }

    // ── Detail ──────────────────────────────────────────────────────

    public function getDetail(Request $request)
    {
        $workout = Workout::with([
            'workoutDay' => fn ($q) => $q->orderBy('sequence'),
            'workoutDay.blocks' => fn ($q) => $q->orderBy('order'),
            'workoutDay.blocks.exercises.exercise',
            'workoutDay.workoutDayExercise.exercise',
        ])->find($request->id);

        if (!$workout) {
            return json_message_response('Workout not found', 404);
        }

        $days = $workout->workoutDay->map(function ($day) {
            $blocks = $day->blocks->map(function ($block) {
                return [
                    'id'           => $block->id,
                    'title'        => $block->title,
                    'order'        => $block->order,
                    'exercises'    => $block->exercises->map(fn ($e) => $this->mapExercise($e))->values(),
                ];
            });

            $unassigned = $day->workoutDayExercise
                ->filter(fn ($e) => !$e->workout_day_block_id)
                ->sortBy('sequence')
                ->values()
                ->map(fn ($e) => $this->mapExercise($e));

            if ($unassigned->isNotEmpty()) {
                $blocks->push([
                    'id'           => null,
                    'title'        => 'Sin agrupar',
                    'order'        => 999,
                    'exercises'    => $unassigned,
                ]);
            }

            return [
                'id'        => $day->id,
                'sequence'  => $day->sequence,
                'is_rest'   => $day->is_rest,
                'blocks'    => $blocks->values(),
            ];
        });

        return json_custom_response([
            'data' => [
                'id'            => $workout->id,
                'title'         => $workout->title,
                'slug'          => $workout->slug,
                'description'   => $workout->description,
                'workout_type_id' => $workout->workout_type_id,
                'level_id'      => $workout->level_id,
                'is_premium'    => $workout->is_premium,
                'visibility'    => $workout->visibility,
                'status'        => $workout->status,
                'days'          => $days,
            ],
        ]);
    }

    private function mapExercise($e)
    {
        $exercise = $e->exercise;
        return [
            'id'                => $e->id,
            'exercise_id'       => $e->exercise_id,
            'workout_day_block_id' => $e->workout_day_block_id,
            'sequence'          => $e->sequence,
            'sets'              => $e->sets,
            'duration'          => $e->duration,
            'title'             => optional($exercise)->title,
            'exercise_image'    => getSingleMedia($exercise, 'exercise_image', null),
        ];
    }

    // ── Days ────────────────────────────────────────────────────────

    public function storeDay(Request $request)
    {
        $request->validate([
            'workout_id' => 'required|exists:workouts,id',
        ]);

        $workout_id = $request->workout_id;
        $maxSeq = WorkoutDay::where('workout_id', $workout_id)->max('sequence') ?? -1;

        $day = WorkoutDay::create([
            'workout_id' => $workout_id,
            'sequence'   => $maxSeq + 1,
            'is_rest'    => 0,
        ]);

        return json_custom_response(['data' => $day], 201);
    }

    public function updateDay(Request $request)
    {
        $request->validate([
            'id'       => 'required|exists:workout_days,id',
            'is_rest'  => 'nullable|boolean',
            'sequence' => 'nullable|integer',
        ]);

        $day = WorkoutDay::findOrFail($request->id);
        $data = array_filter([
            'is_rest'  => $request->has('is_rest') ? (int) $request->is_rest : null,
            'sequence' => $request->has('sequence') ? (int) $request->sequence : null,
        ], fn ($v) => $v !== null);

        $day->update($data);

        return json_custom_response(['data' => $day]);
    }

    public function destroyDay(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_days,id',
        ]);

        WorkoutDay::findOrFail($request->id)->delete();

        return json_message_response('Day deleted');
    }

    // ── Blocks ──────────────────────────────────────────────────────

    public function storeBlock(Request $request)
    {
        $request->validate([
            'workout_day_id' => 'required|exists:workout_days,id',
            'title'          => 'required|string|max:255',
        ]);

        $maxOrder = WorkoutDayBlock::where('workout_day_id', $request->workout_day_id)->max('order') ?? 0;

        $block = WorkoutDayBlock::create([
            'workout_day_id' => $request->workout_day_id,
            'title'          => $request->title,
            'order'          => $maxOrder + 1,
        ]);

        return json_custom_response(['data' => $block], 201);
    }

    public function updateBlock(Request $request)
    {
        $request->validate([
            'id'    => 'required|exists:workout_day_blocks,id',
            'title' => 'sometimes|string|max:255',
            'order' => 'sometimes|integer',
        ]);

        $block = WorkoutDayBlock::findOrFail($request->id);
        $block->update($request->only(['title', 'order']));

        return json_custom_response(['data' => $block]);
    }

    public function destroyBlock(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_day_blocks,id',
        ]);

        WorkoutDayBlock::findOrFail($request->id)->delete();

        return json_message_response('Block deleted');
    }

    // ── Exercises ───────────────────────────────────────────────────

    public function storeExercise(Request $request)
    {
        $request->validate([
            'workout_day_id'       => 'required|exists:workout_days,id',
            'exercise_id'          => 'required|exists:exercises,id',
            'workout_day_block_id' => 'nullable|exists:workout_day_blocks,id',
        ]);

        $day = WorkoutDay::findOrFail($request->workout_day_id);

        if ($request->workout_day_block_id) {
            $maxSeq = WorkoutDayExercise::where('workout_day_block_id', $request->workout_day_block_id)->max('sequence') ?? 0;
        } else {
            $maxSeq = WorkoutDayExercise::where('workout_day_id', $request->workout_day_id)
                ->whereNull('workout_day_block_id')
                ->max('sequence') ?? 0;
        }

        $ex = WorkoutDayExercise::create([
            'workout_id'             => $day->workout_id,
            'workout_day_id'         => $request->workout_day_id,
            'exercise_id'            => $request->exercise_id,
            'workout_day_block_id'   => $request->workout_day_block_id,
            'sequence'               => $maxSeq + 1,
            'sets'                   => $request->sets ?? null,
            'duration'               => $request->duration ?? null,
        ]);

        return json_custom_response(['data' => new \App\Http\Resources\WorkoutDayExerciseResource($ex)], 201);
    }

    public function updateExercise(Request $request)
    {
        $request->validate([
            'id'       => 'required|exists:workout_day_exercises,id',
            'sets'     => 'nullable|integer',
            'duration' => 'nullable|integer',
            'sequence' => 'nullable|integer',
        ]);

        $ex = WorkoutDayExercise::findOrFail($request->id);
        $ex->update(array_filter([
            'sets'     => $request->sets,
            'duration' => $request->duration,
            'sequence' => $request->sequence,
        ], fn ($v) => $v !== null));

        return json_custom_response(['data' => new \App\Http\Resources\WorkoutDayExerciseResource($ex)]);
    }

    public function destroyExercise(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_day_exercises,id',
        ]);

        WorkoutDayExercise::findOrFail($request->id)->delete();

        return json_message_response('Exercise removed');
    }
}
