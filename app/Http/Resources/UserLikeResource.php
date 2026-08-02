<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserLikeResource extends JsonResource
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
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'posting_id'        => $this->posting_id,
            'display_name'      => optional($this->user)->display_name,
            'username'          => optional($this->user)->username,            
            'profile_image'     => getSingleMedia(optional($this->user), 'profile_image', null),
            'users'             => new PostingUserResource($this->user),
        ];
    }
}
