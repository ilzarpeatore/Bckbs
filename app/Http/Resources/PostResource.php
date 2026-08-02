<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tags;
use App\Models\Category;
use App\Models\BlogCategory;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        $tags_name = null;
        if (isset($this->tags_id)) {
            $tags_name = Tags::whereIn('id', $this->tags_id)->get()->map(function ($item) {
                return [
                    'id'    => $item->id,
                    'title' => $item->title,
                ];
            });
        }

        $category_name = null;
        if (isset($this->category_ids)) {
            $category_name = Category::whereIn('id', $this->category_ids)->get()->map(function ($item) {
                return [
                    'id'    => $item->id,
                    'title' => $item->title,
                ];
            });
        }

        $blog_category = null;
        if ($this->blog_category_id) {
            $bc = BlogCategory::find($this->blog_category_id);
            if ($bc) {
                $blog_category = [
                    'id'    => $bc->id,
                    'title' => $bc->title,
                    'slug'  => $bc->slug,
                ];
            }
        }

        $image = $this->getFirstMediaUrl('post_image');
        if (!$image) {
            $image = $this->getFirstMediaUrl('image');
        }

        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'content'           => $this->content,
            'bibliography'      => $this->bibliography,
            'tags_name'         => $tags_name,
            'tags_id'           => $this->tags_id,
            'category_ids'      => $this->category_ids,
            'category_name'     => $category_name,
            'blog_category_id'  => $this->blog_category_id,
            'blog_category'     => $blog_category,
            'datetime'          => $this->datetime,
            'status'            => $this->status,
            'is_featured'       => $this->is_featured,
            'post_image'        => $image,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
