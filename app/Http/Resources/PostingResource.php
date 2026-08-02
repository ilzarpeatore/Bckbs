<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostingResource extends JsonResource
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
            'id'                    => $this->id,
            'description'           => $this->description,
            'status'                => $this->status,
            'user_id'               => $this->user_id,
            // 'posting_media'         => getAttachments($this->getMedia('posting_media')),
            'posting_media_array'   => getAttachmentArray( $this->getMedia('posting_media'), null),
            'users'                 => new PostingUserResource($this->whenLoaded('user')),
            'posting_like_count'    => $this->posting_like_count ?? 0,
            'posting_comment_count' => $this->comment_count ?? 0,
            'can_edit'              => $this->can_edit,
            'created_at'            => timeAgoFormate($this->created_at),
            'is_liked'              => $this->whenLoaded('postingLike', fn() => true, false),
            'is_bookmark'           => $this->whenLoaded('postingBookmark', fn() => true, false),
        ];
    }
}
