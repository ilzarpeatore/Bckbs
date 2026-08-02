<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $auth_user = auth()->user();
        $class_schedule_plan = $auth_user->classSchedulePlan->where('class_schedule_id',$this->id)->first();

        return [
            'id'            => $this->id,
            'class_name'    => $this->class_name,
            'workout_id'    => $this->workout_id,
            'workout'       => optional($this->workout)->title,
            'workout_title' => $this->workout_title,
            'workout_type'  => $this->workout_type,
            'start_date'    => $this->start_date,
            'end_date'      => $this->end_date,
            'start_time'    => $this->start_time,
            'end_time'      => $this->end_time,
            'name'          => $this->name,
            'link'          => $this->link,
            'is_paid'       => (string) $this->is_paid,
            'is_paid_bool'  => $this->is_paid,
            'price'         => $this->price,
            'is_class_schedule_plan' => isset($class_schedule_plan) ? 1 : 0,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
