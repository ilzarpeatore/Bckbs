<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GameScoreDataResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'user_id'    => $this->user_id,
            'user_name'    => optional($this->users)->display_name,
            'score'         => $this->score,
            'country_code'  => $this->country_code,
            'flag_url'      => "https://flagcdn.com/w40/" . strtolower($this->country_code) . ".png",
        ];
    }
}
