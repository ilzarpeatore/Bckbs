<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\BodyPart;

class ExerciseDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $video_url = $this->video_url;
        if( $this->video_type == 'upload_video' ) {
            $video_url = getMediaFileExit($this, 'exercise_video') ? getSingleMedia($this, 'exercise_video', null) : null;
        }
        $selected_bodypart = null;
        if(isset($this->bodypart_ids)) {
            $selected_bodypart = BodyPart::whereIn('id', $this->bodypart_ids)->get()->map(function ($item) {
                // return $item;
                return [
                    'id'    => $item->id,
                    'title' => $item->title,
                    'bodypart_image'   => getSingleMedia($item, 'bodypart_image',null),
                ];
            });
        }
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'status'            => $this->status,
            'is_premium'        => $this->is_premium,
            'exercise_image'    => getSingleMedia($this, 'exercise_image', null),
            'video_type'        => $this->video_type,
            'video_url'         => $video_url ?? null,
            'bodypart_name'     => $selected_bodypart,
            'duration'          => $this->duration,
            'sets'              => $this->sets,
            'based'             => $this->based,
            'type'              => $this->type,
            'equipment_id'      => $this->equipment_id,
            'equipment_title'   => optional($this->equipment)->title,
            'equipment_image'   => getSingleMedia($this->equipment, 'equipment_image',null),
            'level_id'          => $this->level_id,
            'level_title'       => optional($this->level)->title,
            'instruction'       => $this->instruction,
            'seconds_per_rep'   => $this->seconds_per_rep ?? 4,
            'tips'              => $this->tips,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}