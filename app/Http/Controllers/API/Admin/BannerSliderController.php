<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\BannerSlider;
use App\Http\Resources\BannerSliderResource;
use Illuminate\Http\Request;

class BannerSliderController extends BaseController
{
    protected function getModelClass(): string
    {
        return BannerSlider::class;
    }

    protected function getResourceClass(): string
    {
        return BannerSliderResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'      => 'required|string|max:255',
            'slug'       => 'sometimes|string|max:255|unique:banner_sliders,slug,' . $id,
            'workout_id' => 'nullable|exists:workouts,id',
            'type'       => 'nullable|string',
            'url'        => 'nullable|string',
            'status'     => 'sometimes|in:active,inactive',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('bannerslider_image')) {
            $item->clearMediaCollection('bannerslider_image');
            $item->addMediaFromRequest('bannerslider_image')->toMediaCollection('bannerslider_image');
        }
    }
}
