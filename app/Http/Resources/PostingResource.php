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
            // FIX 2026-08-13: whenLoaded() solo comprueba si la relacion fue
            // cargada (siempre lo esta, el controller la eager-carga filtrada
            // por auth()->id()), no si tiene filas - por eso like/bookmark
            // salian marcados por defecto para cualquier usuario. Hay que
            // comprobar si la coleccion cargada (ya filtrada por user_id) tiene
            // elementos.
            'is_liked'              => $this->whenLoaded('postingLike', fn() => $this->postingLike->isNotEmpty(), false),
            'is_bookmark'           => $this->whenLoaded('postingBookmark', fn() => $this->postingBookmark->isNotEmpty(), false),
        ];
    }
}
