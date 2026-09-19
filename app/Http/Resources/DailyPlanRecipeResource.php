<?php

namespace App\Http\Resources;

use App\Models\FatSecretRecipeCache;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyPlanRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'daily_plan_id' => $this->daily_plan_id,
            'recipe_id'     => $this->recipe_id,
            'fatsecret_recipe_id' => $this->fatsecret_recipe_id,
            'calories'      => round($this->calories),
            'protein'       => round($this->protein),
            'fats'          => round($this->fats),
            'carbs'         => round($this->carbs),
            'meal_type'     => $this->meal_type,
            'recipe'        => $this->resolveRecipePreview(),
            'is_complete'   => $this->is_complete,
            'is_coach_assigned' => !is_null($this->assigned_by_user_id),
            'assigned_by'   => $this->whenLoaded('assignedBy', fn () => [
                'id'   => $this->assignedBy->id,
                'name' => $this->assignedBy->display_name ?: $this->assignedBy->email,
            ]),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }

    /**
     * 'recipe' con el MISMO shape para ambos orígenes (propia o FatSecret),
     * para que la app no necesite ninguna rama distinta al pintar el
     * calendario -- ver docs/FATSECRET_INTEGRATION.md sección 9.
     *
     * IMPORTANTE: para el caso FatSecret esto es una lectura PASIVA del
     * cache-aside (FatSecretRecipeCache::find), nunca llama a la API ni
     * fuerza un refresco -- si esta vista llamara a getOrRefresh() aquí,
     * listar una semana de calendario dispararía una llamada real por cada
     * comida mostrada. El detalle completo (pasos/ingredientes), que sí
     * necesita datos frescos de verdad, se pide aparte vía
     * FatSecretController::show() cuando el usuario abre esa receta.
     */
    private function resolveRecipePreview(): ?array
    {
        if (isset($this->recipe)) {
            return (new RecipePlanResource($this->recipe))->resolve();
        }

        if (!$this->fatsecret_recipe_id) {
            return null;
        }

        $cached = FatSecretRecipeCache::find($this->fatsecret_recipe_id);

        return [
            'id'           => $this->fatsecret_recipe_id,
            'source'       => 'fatsecret',
            'title'        => $cached->name ?? null,
            'recipe_image' => $cached->image_url ?? null,
            'calories'     => round($this->calories),
            'protein'      => round($this->protein),
            'fats'         => round($this->fats),
            'carbs'        => round($this->carbs),
        ];
    }
}
