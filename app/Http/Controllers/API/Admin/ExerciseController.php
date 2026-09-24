<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Exercise;
use App\Http\Resources\ExerciseResource;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use Illuminate\Validation\Rule;

class ExerciseController extends BaseController
{
    protected function getModelClass(): string
    {
        return Exercise::class;
    }

    protected function getResourceClass(): string
    {
        return ExerciseResource::class;
    }

    public function index(Request $request)
    {
        $model = $this->getModel();
        $query = $model->query();

        if (method_exists($model, 'scopeSearch')) {
            $query->search($request);
        } elseif ($request->filled('search')) {
            $columns = array_values(array_filter($model->getFillable(), fn ($f) => !in_array($f, ['password', 'remember_token'])));
            FuzzySearch::apply($query, $columns, $request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtros de la Biblioteca de ejercicios (pedido explícito 2026-09-20,
        // WorkoutTemplateViewer -- compartido por training-programs, workout-
        // templates y el detalle de sesión de cliente). Todos opcionales y
        // combinables entre sí.
        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->equipment_id);
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('exercise_type')) {
            $query->where('exercise_type', $request->exercise_type);
        }

        // bodypart_ids es una columna `text` con un array JSON en la mayoría
        // de filas (1512 de 1548 con dato, ej. "[1,2]") -- pero 36 filas
        // heredadas de un formato antiguo guardan un escalar JSON, ej. "1"
        // literal (comprobado contra la BD real 2026-09-20 antes de dar esto
        // por bueno: whereJsonContains() por sí solo NO matchea esas 36,
        // JSON_CONTAINS compara tipos, y "1" (string JSON) no es lo mismo
        // que 1 (number JSON) para MySQL). Cubrir ambos formatos.
        if ($request->filled('bodypart_id')) {
            $bodypartId = (int) $request->bodypart_id;
            $query->where(function ($q) use ($bodypartId) {
                $q->whereJsonContains('bodypart_ids', $bodypartId)
                    ->orWhere('bodypart_ids', '"'.$bodypartId.'"');
            });
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 5000) {
            $perPage = 5000;
        }

        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        $resourceClass = $this->getResourceClass();
        $items = $resourceClass::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'           => 'required|string|max:255',
            'slug'            => 'sometimes|string|max:255|unique:exercises,slug,' . $id,
            'instruction'     => 'nullable|string',
            'tips'            => 'nullable|string',
            'video_type'      => 'sometimes|in:youtube,vimeo',
            'video_url'       => 'nullable|string',
            'bodypart_ids'    => 'nullable|string',
            'duration'        => 'nullable|string',
            'sets'            => 'nullable|string',
            'exercise_type'   => ['nullable', Rule::in(array_keys(Exercise::EXERCISE_TYPES))],
            'equipment_id'    => 'nullable|exists:equipment,id',
            'level_id'        => 'nullable|exists:levels,id',
            'is_premium'      => 'sometimes|boolean',
            'seconds_per_rep' => 'nullable|numeric',
            'status'          => 'sometimes|in:active,inactive',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('exercise_image');
            $item->addMediaFromRequest('image')->toMediaCollection('exercise_image');
        }
    }
}
