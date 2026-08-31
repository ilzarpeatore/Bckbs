<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserProfileResource;

class UserDetailResource extends JsonResource
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
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'display_name'      => $this->display_name,
            'email'             => $this->email,
            'username'          => $this->username,
            'gender'            => $this->gender,
            'status'            => $this->status,
            'user_type'         => $this->user_type,
            'phone_number'      => $this->phone_number,
            'player_id'         => $this->player_id,
            'profile_image'     => getSingleMedia($this, 'profile_image',null),
            'login_type'        => $this->login_type,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'user_profile'      => isset($this->userProfile) ? new UserProfileResource($this->userProfile) : null,
            'is_subscribe'      => $this->is_subscribe,
            'is_personal_client' => (bool) $this->is_personal_client,
        ];
    }
}
