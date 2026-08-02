<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\BodyPart;

class ExerciseResource extends JsonResource
{
    private static $bodypartTitles = null;

    public function toArray($request)
    {
        $bodypartNames = null;
        if (!empty($this->bodypart_ids)) {
            $ids = is_string($this->bodypart_ids) ? json_decode($this->bodypart_ids, true) : $this->bodypart_ids;
            if (is_array($ids) && count($ids)) {
                if (self::$bodypartTitles === null) {
                    self::$bodypartTitles = BodyPart::pluck('title', 'id');
                }
                $names = collect($ids)->map(fn($id) => self::$bodypartTitles[$id] ?? null)->filter()->implode(', ');
                $bodypartNames = $names ?: null;
            }
        }

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'status'          => $this->status,
            'is_premium'      => $this->is_premium,
            'exercise_image'  => getSingleMedia($this, 'exercise_image', null),
            'level_id'        => $this->level_id,
            'level_title'     => optional($this->level)->title,
            'equipment_id'    => $this->equipment_id,
            'equipment_title' => optional($this->equipment)->title,
            'bodypart_ids'    => $this->bodypart_ids,
            'bodypart_names'  => $bodypartNames,
            'type'            => $this->type,
            'based'           => $this->based,
            'duration'        => $this->duration,
            'sets'            => $this->sets,
            'instruction'     => $this->instruction,
            'tips'            => $this->tips,
            'video_type'      => $this->video_type,
            'video_url'       => $this->video_url,
            'seconds_per_rep' => $this->seconds_per_rep ?? 4,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
