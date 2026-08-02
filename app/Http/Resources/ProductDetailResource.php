<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
//use App\Models\Product;

class ProductDetailResource  extends JsonResource
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
            'id'                       => $this->id,
            'title'                    => $this->title,
            'description'              => $this->description,
            'affiliate_link'           => $this->affiliate_link,
            'price'                    => $this->price,
            'product_image'            => getSingleMedia($this, 'product_image', null),
            'productcategory_id'       => $this->productcategory_id,
            'featured'                 => $this->featured,
            'status'                   => $this->status,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}