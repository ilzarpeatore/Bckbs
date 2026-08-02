<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentReplyResource extends JsonResource
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
            'comment'           => $this->comment,
            'user_id'           => $this->user_id,
            'comment_id'        => $this->comment_id,
            'users'             => new PostingUserResource($this->user),
            'can_edit'          => $this->can_edit,
            'created_at'        => timeAgoFormate($this->created_at),
        ];
    }
}
