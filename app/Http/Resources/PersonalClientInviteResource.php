<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonalClientInviteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'first_name'  => $this->first_name,
            'last_name'   => $this->last_name,
            'email'       => $this->email,
            'used_at'     => $this->used_at,
            'used_by'     => optional($this->usedBy)->display_name,
            'expires_at'  => $this->expires_at,
            'is_used'     => $this->isUsed(),
            'is_expired'  => $this->isExpired(),
            'created_at'  => $this->created_at,
        ];
    }
}
