<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeStepResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'instruction' => $this->instruction,
            'sequence'    => $this->sequence,
        ];
    }
}