<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Post;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;

class PostController extends BaseController
{
    protected function getModelClass(): string
    {
        return Post::class;
    }

    protected function getResourceClass(): string
    {
        return PostResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'            => 'required|string|max:255',
            'slug'             => 'sometimes|string|max:255|unique:posts,slug,' . $id,
            'tags_id'          => 'nullable|string',
            'category_ids'     => 'nullable|string',
            'blog_category_id' => 'nullable|integer|exists:blog_categories,id',
            'datetime'         => 'nullable|date',
            'channel'          => 'sometimes|in:app,web,both',
            'is_featured'      => 'sometimes|boolean',
            'description'      => 'nullable|string',
            'content'          => 'nullable|string',
            'bibliography'     => 'nullable|string',
            'status'           => 'sometimes|in:active,inactive,publish,draft',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        // PostResource::toArray() prioriza la colección 'post_image' sobre
        // 'image' si ambas existen (getFirstMediaUrl('post_image') primero).
        // Los posts sembrados el 2026-08-20 ya traían 'post_image' -- subir
        // una portada nueva a 'image' quedaba invisible en la API porque
        // 'post_image' seguía ahí tapándola. Se limpia siempre la otra
        // colección para que solo exista una imagen de portada activa a la
        // vez, sea cual sea el campo por el que llegó.
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('image');
            $item->clearMediaCollection('post_image');
            $item->addMedia($request->file('image'))->toMediaCollection('image');
        }

        if ($request->hasFile('post_image')) {
            $item->clearMediaCollection('post_image');
            $item->clearMediaCollection('image');
            $item->addMedia($request->file('post_image'))->toMediaCollection('post_image');
        }
    }

    public function uploadCoverImage(Request $request, $id)
    {
        $item = Post::find($id);

        if (!$item) {
            return json_message_response('Post not found.', 404);
        }

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $item->clearMediaCollection('image');
        // Ver comentario en afterSave() -- 'post_image' shadows 'image' en
        // PostResource, así que hay que limpiarla aquí también.
        $item->clearMediaCollection('post_image');
        $item->addMedia($request->file('image'))->toMediaCollection('image');

        $resourceClass = $this->getResourceClass();
        return json_custom_response([
            'message' => 'Cover image updated.',
            'data'    => new $resourceClass($item),
        ]);
    }
}
